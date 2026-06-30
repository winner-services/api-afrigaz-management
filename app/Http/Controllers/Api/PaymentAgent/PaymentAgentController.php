<?php

namespace App\Http\Controllers\Api\PaymentAgent;

use App\Http\Controllers\Controller;
use App\Models\About;
use App\Models\Agent;
use App\Models\Avance;
use App\Models\CashTransaction;
use App\Models\Currency;
use App\Models\PayementAgent;
use App\Models\PayementAgentDetail;
use App\Services\ImageService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PaymentAgentController extends Controller
{
    protected $imageService;

    public function __construct(ImageService $imageService)
    {
        $this->imageService = $imageService;
    }
    public function genererMasseSalariale(Request $request)
    {
        $request->validate([
            'mois_concerne' => 'required|date_format:Y-m',
        ]);

        $moisConcerne = $request->mois_concerne;

        try {
            $dejaGenere = PayementAgent::where('mois_concerne', $moisConcerne)->exists();
            if ($dejaGenere) {
                return response()->json([
                    'status' => 422,
                    'success' => false,
                    'message' => 'La paie de ce mois a déjà été générée.'
                ], 422);
            }

            $agents = Agent::with('fonction')->where('status', 'created')->get();

            if ($agents->isEmpty()) {
                return response()->json([
                    'status' => 404,
                    'success' => false,
                    'message' => 'Aucun agent actif trouvé.'
                ], 404);
            }

            return DB::transaction(function () use ($agents, $moisConcerne) {

                $paiementGlobal = PayementAgent::create([
                    'total_net_a_verser' => 0,
                    'total_avances_deduites' => 0,
                    'reste_a_payer' => 0,
                    'total_masse_salariale_brute' => 0,
                    'addedBy' => Auth::user()->id,
                    'mois_concerne' => $moisConcerne,
                    'date_activation' => Carbon::now(),
                    'reference' => 'PAIE-' . $moisConcerne . '-' . strtoupper(uniqid()),
                ]);

                $totalMasseBrute = 0;
                $totalAvancesGlobal = 0;
                $totalNetGlobal = 0;

                foreach ($agents as $agent) {
                    $salaireBase = $agent->fonction ? $agent->fonction->montant : 0;

                    $totalAvancesAgent = Avance::where('agent_id', $agent->id)
                        ->where('mois_concerne', $moisConcerne)
                        ->where('status', 'approuve')
                        ->sum('montant');

                    $netAPayer = $salaireBase - $totalAvancesAgent;

                    PayementAgentDetail::create([
                        'agent_id' => $agent->id,
                        'paiement_id' => $paiementGlobal->id,
                        'salaire_base' => $salaireBase,
                        'total_avances' => $totalAvancesAgent,
                        'net_a_payer' => $netAPayer,
                        'status' => 'en_attente',
                        'reference' => 'PAIE-' . $moisConcerne . '-' . strtoupper(uniqid())
                    ]);

                    $totalMasseBrute += $salaireBase;
                    $totalAvancesGlobal += $totalAvancesAgent;
                    $totalNetGlobal += $netAPayer;
                }

                $paiementGlobal->update([
                    'total_masse_salariale_brute' => $totalMasseBrute,
                    'total_avances_deduites' => $totalAvancesGlobal,
                    'total_net_a_verser' => $totalNetGlobal,
                    'reste_a_payer' => $totalNetGlobal,
                ]);

                return response()->json([
                    'status' => 201,
                    'success' => true,
                    'message' => 'Masse salariale générée avec succès !',
                    'data' => $paiementGlobal
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'success' => false,
                'message' => 'Une erreur est survenue lors de la génération de la paie.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function validerPaiementAgent(Request $request)
    {
        $request->validate([
            'detail_id'          => 'required|integer|exists:payement_agent_details,id',
            'account_id'         => 'required|exists:cash_accounts,id',
            'date_paiement'      => 'nullable|date',
            'type_payment'       => 'nullable|string',
            'reference_paiement' => 'nullable|string',
        ]);

        try {
            $detailId = $request->detail_id;

            $detail = PayementAgentDetail::findOrFail($detailId);

            if ($detail->status === 'paye') {
                return response()->json([
                    'status'  => 422,
                    'success' => false,
                    'message' => 'Ce salaire a déjà été payé pour cet agent.'
                ], 422);
            }

            return DB::transaction(function () use ($request, $detail) {
                $about = About::first();
                if ($about) {
                    $this->imageService->transform($about, ['logo', 'logo2']);
                }

                $lastTransaction = CashTransaction::where('cash_account_id', $request->account_id)
                    ->latest('id')
                    ->lockForUpdate()
                    ->first();

                $currentSolde = $lastTransaction ? $lastTransaction->solde : 0;

                $montantPaye = $detail->net_a_payer;

                $detail->update([
                    'status'             => 'paye',
                    'account_id'         => $request->account_id,
                    'type_payment'       => $request->type_payment,
                    'reference_paiement' => $request->reference_paiement,
                    'confirmedBy'        => Auth::user()->id,
                    'date_paiement'      => $request->date_paiement ?? Carbon::now()
                ]);

                $agent_name = Agent::find($detail->agent_id);

                $paiementGlobal = PayementAgent::findOrFail($detail->paiement_id);

                $nouveauReste = $paiementGlobal->reste_a_payer - $montantPaye;

                $paiementGlobal->update([
                    'reste_a_payer' => $nouveauReste < 0 ? 0 : $nouveauReste
                ]);

                $newSolde = $currentSolde - $montantPaye;

                $transaction = CashTransaction::create([
                    'transaction_date' => Carbon::now(),
                    'cash_account_id' => $request->account_id,
                    'amount' => $montantPaye,
                    'type' => 'Depense',
                    'solde' => $newSolde,
                    'cash_categorie_id' => 2,
                    'reference' => fake()->unique()->numerify('PAYE-#####'),
                    'addedBy' => Auth::user()->id,
                    'reason' => "Paiement salaire mois de {$paiementGlobal->mois_concerne}",
                    'reference_paiement' => $request->reference_paiement ?? '-'
                ]);

                $detail->setAttribute('agent_name', $agent_name ? $agent_name->name : null);

                return response()->json([
                    'status'  => 200,
                    'success' => true,
                    'message' => 'Le paiement de l\'agent a été validé avec succès !',
                    'info_company' => $about,
                    'data'    => $detail
                ], 200);
            });
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 500,
                'success' => false,
                'message' => 'Une erreur est survenue lors de la validation du paiement.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function listerToutAvecDetails(Request $request)
    {
        try {
            $about = About::first();
            if ($about) {
                $this->imageService->transform($about, ['logo', 'logo2']);
            }
            $devise = Currency::where('status', 'created')
                ->orderByRaw("currency_type = 'devise_principale' DESC")
                ->latest()
                ->get();

            $query = PayementAgent::with([
                'details' => function ($q) {
                    $q->with([
                        'agent' => function ($agentQuery) {
                            $agentQuery->with('fonction');
                        },
                        'compte'
                    ]);
                },
                'user'
            ]);

            if ($request->has('mois') && !empty($request->mois)) {
                $request->validate(['mois' => 'date_format:Y-m']);
                $query->where('mois_concerne', $request->mois);
            }

            // $perPage = $request->get('per_page', 15);

            $historiquePaiements = $query->orderBy('mois_concerne', 'desc')->paginate(10);

            $paginatedData = $historiquePaiements->through(function ($paiementGlobal) {
                return [
                    'global' => [
                        'id'                          => $paiementGlobal->id,
                        'reference'                   => $paiementGlobal->reference,
                        'mois_concerne'               => $paiementGlobal->mois_concerne,
                        'total_masse_salariale_brute' => $paiementGlobal->total_masse_salariale_brute,
                        'total_avances_deduites'      => $paiementGlobal->total_avances_deduites,
                        'total_net_a_verser'          => $paiementGlobal->total_net_a_verser,
                        'reste_a_payer'               => $paiementGlobal->reste_a_payer,
                        'total_deja_paye'             => $paiementGlobal->total_net_a_verser - $paiementGlobal->reste_a_payer,
                        'total_decaisse_global'       => $paiementGlobal->total_avances_deduites + ($paiementGlobal->total_net_a_verser - $paiementGlobal->reste_a_payer),
                        'date_activation'             => $paiementGlobal->date_activation,
                        'genere_par'                  => $paiementGlobal->user->name ?? 'Inconnu',
                    ],
                    'details_agents' => $paiementGlobal->details->map(function ($detail) {
                        return [
                            'global_id'          => $detail->paiement_id,
                            'detail_id'          => $detail->id,
                            'agent_id'           => $detail->agent_id,
                            'nom_agent'          => $detail->agent->name ?? 'Anonyme',
                            'genre'              => $detail->agent->gender ?? '-',
                            'fonction'           => $detail->agent->fonction->designation ?? 'Aucune',
                            'salaire_base'       => $detail->salaire_base,
                            'total_avances'      => $detail->total_avances,
                            'net_a_payer'        => $detail->net_a_payer,
                            'montant_paye'       => $detail->status === 'paye' ? $detail->net_a_payer : 0,
                            'status'             => $detail->status,
                            'date_paiement'      => $detail->date_paiement,
                            'reference_paiement' => $detail->reference_paiement,
                            'type_payment'       => $detail->type_payment,
                            'account_id'         => $detail->account_id,
                            'account_name'       => $detail->compte->designation ?? ($detail->compte->nom ?? 'Non payé'),
                            'reference'         => $detail->reference
                        ];
                    })
                ];
            });

            return response()->json([
                'status'  => 200,
                'success' => true,
                'info_company' => $about,
                'devise'  => $devise,
                'data'    => $paginatedData
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 500,
                'success' => false,
                'message' => 'Une erreur est survenue lors du chargement de l\'historique des paies.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function listerDetailsAvancesAgentsEnAttente()
    {
        try {
            $about = About::first();
            if ($about) {
                $this->imageService->transform($about, ['logo', 'logo2']);
            }
            $devise = Currency::where('status', 'created')
                ->orderByRaw("currency_type = 'devise_principale' DESC")
                ->latest()
                ->get();
            $moisConcerne = request('month');

            if (empty($moisConcerne)) {
                $dernierPaiement = PayementAgent::orderBy('mois_concerne', 'desc')->first();
                $moisConcerne = $dernierPaiement ? $dernierPaiement->mois_concerne : Carbon::now()->format('Y-m');
            } else {
                $validator = Validator::make(['mois_concerne' => $moisConcerne], [
                    'mois_concerne' => 'date_format:Y-m'
                ]);

                if ($validator->fails()) {
                    return response()->json([
                        'status'  => 422,
                        'success' => false,
                        'message' => 'Le format du mois_concerne est invalide (Attendu: YYYY-MM).',
                        'errors'  => $validator->errors()
                    ], 422);
                }
            }

            $detailsPaie = PayementAgentDetail::where('status', 'en_attente')
                ->whereHas('paiementGlobal', function ($query) use ($moisConcerne) {
                    $query->where('mois_concerne', $moisConcerne);
                })
                ->with([
                    'agent' => function ($query) use ($moisConcerne) {
                        $query->with(['fonction', 'avances' => function ($avanceQuery) use ($moisConcerne) {
                            $avanceQuery->where('mois_concerne', $moisConcerne)
                                ->where('status', 'approuve')
                                ->orderBy('date_versement', 'asc');
                        }]);
                    }
                ])
                ->get();

            $agentsFormates = $detailsPaie->map(function ($detail) {
                $agent = $detail->agent;
                $sommeReelleAvances = $agent && $agent->avances ? (float) $agent->avances->sum('montant') : 0.0;

                $salaireBase = (float) $detail->salaire_base;
                $netAPayerRecalcule = $salaireBase - $sommeReelleAvances;

                return [
                    'detail_id'               => $detail->id,
                    'agent_id'               => $detail->agent_id,
                    'nom_agent'              => $agent->name ?? 'Anonyme',
                    'fonction'               => $agent->fonction->designation ?? 'Aucune',
                    'salaire_base'           => number_format($salaireBase, 2, '.', ''),

                    'total_avances_deduites' => number_format($sommeReelleAvances, 2, '.', ''),
                    'net_a_payer'            => number_format($netAPayerRecalcule < 0 ? 0 : $netAPayerRecalcule, 2, '.', ''),

                    'status_paie'            => $detail->status,

                    'historique_avances'     => $agent && $agent->avances ? $agent->avances->map(function ($avance) {
                        return [
                            'avance_id'      => $avance->id,
                            'montant'        => $avance->montant,
                            'date_versement' => $avance->date_versement,
                            'reference'      => $avance->reference ?? '-',
                            'status'         => $avance->status
                        ];
                    }) : []
                ];
            });

            return response()->json([
                'status'         => 200,
                'success'        => true,
                'info_company' => $about,
                'devise' => $devise,
                'mois_concerne'    => $moisConcerne,
                'data'           => $agentsFormates
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 500,
                'success' => false,
                'message' => 'Une erreur est survenue lors de la récupération du détail des avances en attente.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}
