<?php

namespace App\Http\Controllers\Api\PaymentAgent\Avance;

use App\Http\Controllers\Controller;
use App\Models\About;
use App\Models\Agent;
use App\Models\Avance;
use App\Models\CashTransaction;
use App\Models\Currency;
use App\Services\ImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AvanceController extends Controller
{
    protected $imageService;

    public function __construct(ImageService $imageService)
    {
        $this->imageService = $imageService;
    }
    public function listeAvances()
    {
        $devise = Currency::where('status', 'created')
            ->orderByRaw("currency_type = 'devise_principale' DESC")
            ->latest()
            ->get();
        $moisEnCours = \Carbon\Carbon::now()->format('Y-m');

        $mois = request('mois') ?: $moisEnCours;

        try {
            $query = Avance::with(['agent.fonction', 'user']);

            if ($mois) {
                $query->where('mois_concerne', $mois);
            }

            $avances = $query->latest('id')->paginate(15);

            return response()->json([
                'status' => 200,
                'success' => true,
                'devise' => $devise,
                'data' => $avances
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'success' => false,
                'error' => 'Erreur lors de la récupération des avances.',
                'debug' => $e->getMessage()
            ], 500);
        }
    }
    public function enregistrerAvance(Request $request)
    {
        $request->validate([
            'agent_id' => 'required|exists:agents,id',
            'account_id' => 'required|exists:cash_accounts,id',
            'category_id' => 'nullable|integer',
            'montant' => 'required|numeric|min:1',
            'mois_concerne' => 'required|date_format:Y-m',
            'reference_paiement' => 'nullable',
            'type_payment' => 'nullable'
        ]);

        try {

            $about = About::first();

            if ($about) {
                $this->imageService->transform($about, ['logo', 'logo2']);
            }
            $agent = Agent::with('fonction')->findOrFail($request->agent_id);
            $salaireBase = $agent->fonction->montant ?? 0;

            if (!$salaireBase) {
                return response()->json([
                    'status' => 422,
                    'success' => false,
                    'error' => 'Cet agent n\'a pas de salaire défini pour sa fonction.'
                ], 422);
            }

            $dejaPris = Avance::where('agent_id', $agent->id)
                ->where('mois_concerne', $request->mois_concerne)
                ->where('status', 'approuve')
                ->sum('montant');

            $limiteMaximale = $salaireBase * 0.80;

            if (($dejaPris + $request->montant) > $limiteMaximale) {
                return response()->json([
                    'status' => 422,
                    'success' => false,
                    'error' => "Demande refusée. Le total des avances ({$dejaPris} + {$request->montant}) dépasserait la limite autorisée de 80% du salaire ({$limiteMaximale})."
                ], 422);
            }

            $avance = DB::transaction(function () use ($request, $agent) {

                $avance = Avance::create([
                    'agent_id' => $agent->id,
                    'montant' => $request->montant,
                    'mois_concerne' => $request->mois_concerne,
                    'date_versement' => \Carbon\Carbon::now()->toDateString(),
                    'status' => 'approuve',
                    'addedBy' => Auth::user()->id,
                    'reference' => fake()->randomNumber(5),
                    'account_id' => $request->account_id
                ]);

                $lastTransaction = CashTransaction::where('cash_account_id', $request->account_id)
                    ->latest('id')
                    ->lockForUpdate()
                    ->first();

                $currentSolde = $lastTransaction ? $lastTransaction->solde : 0;

                $newSolde = $currentSolde - $request->montant;

                $transaction = CashTransaction::create([
                    'transaction_date' => Carbon::now(),
                    'cash_account_id' => $request->account_id,
                    'amount' => $request->montant,
                    'type' => 'Depense',
                    'solde' => $newSolde,
                    'cash_categorie_id' => 2,
                    'reference' => fake()->unique()->numerify('AVANCE-#####'),
                    'addedBy' => Auth::user()->id,
                    'reason' => "Paiement avance sur salaire de {$agent->name}",
                    'reference_paiement' => $request->reference_paiement ?? '-'
                ]);

                return $avance;
            });

            return response()->json([
                'status' => 201,
                'success' => true,
                'message' => 'Avance accordée avec succès !',
                'info_company' => $about,
                'data' => $avance
            ], 201);
        } catch (\Exception $e) {

            return response()->json([
                'status' => 500,
                'success' => false,
                'error' => 'Une erreur interne est survenue lors du traitement de l\'avance.',
                'debug' => $e->getMessage()
            ], 500);
        }
    }
}
