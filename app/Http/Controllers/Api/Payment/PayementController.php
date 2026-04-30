<?php

namespace App\Http\Controllers\Api\Payment;

use App\Http\Controllers\Controller;
use App\Models\About;
use App\Models\CashTransaction;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerDebt;
use App\Models\CustomerDebtPayment;
use App\Models\DebtDistributor;
use App\Models\Distributor;
use App\Models\PaymentDistributor;
use App\Models\Sale;
use App\Services\ImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class PayementController extends Controller
{
    protected $imageService;

    public function __construct(ImageService $imageService)
    {
        $this->imageService = $imageService;
    }
    #[OA\Post(
        path: '/api/v1/debtPaymentStore',
        summary: 'Créer',
        tags: ['Payment'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['customer_id', 'distributor_id', 'paid_amount', 'account_id', 'transaction_date', 'due_anount'],
                properties: [
                    new OA\Property(property: "customer_id", type: "integer", example: 1),
                    new OA\Property(property: "distributor_id", type: "integer", example: 1),
                    new OA\Property(property: "paid_amount", type: "number", format: "float", example: 100.00),
                    new OA\Property(property: "due_anount", type: "number", format: "float", example: 100.00),
                    new OA\Property(property: "account_id", type: "integer", example: 1),
                    new OA\Property(property: "transaction_date", type: "string", format: "date", example: "2023-01-01"),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Données créées avec succès'
            ),
            new OA\Response(
                response: 422,
                description: 'Validation des données échouée'
            ),
            new OA\Response(
                response: 500,
                description: 'Erreur serveur'
            )
        ]
    )]

    public function paymentDebt(Request $request)
    {
        $about = About::first();

        if ($about) {
            $this->imageService->transform($about, ['logo', 'logo2']);
        }

        $devise = Currency::where('status', 'created')
            ->orderByRaw("currency_type = 'devise_principale' DESC")
            ->latest()
            ->get();

        $request->validate([
            'distributor_id' => 'nullable|exists:distributors,id',
            'customer_id' => 'nullable|exists:customers,id',
            'paid_amount' => 'required|numeric|min:0.01',
            'account_id' => 'required|exists:cash_accounts,id',
            'transaction_date' => 'nullable|date',
        ]);

        if (!$request->distributor_id && !$request->customer_id) {
            return response()->json([
                'status' => 422,
                'success' => false,
                'message' => 'Veuillez fournir un distributeur ou un client.'
            ], 422);
        }

        if ($request->distributor_id && $request->customer_id) {
            return response()->json([
                'status' => 422,
                'success' => false,
                'message' => 'Choisir soit distributeur soit client, pas les deux.'
            ], 422);
        }

        try {
            DB::beginTransaction();

            $remainingAmount = (float) $request->paid_amount;
            $totalPaid = 0;

            $lastTransaction = CashTransaction::where('cash_account_id', $request->account_id)
                ->latest('id')
                ->first();

            $currentSolde = $lastTransaction?->solde ?? 0;

            if ($request->distributor_id) {

                $debts = DebtDistributor::where('distributor_id', $request->distributor_id)
                    ->whereIn('status', ['pending', 'partial'])
                    ->orderBy('transaction_date', 'asc')
                    ->lockForUpdate()
                    ->get();

                $paymentModel = PaymentDistributor::class;
                $foreignKey = 'debt_distributor_id';
                $cashCategory = 5;
                $label = 'Distributeur #' . $request->distributor_id;

                $buyerName = Distributor::find($request->distributor_id)?->name;
            } else {

                $debts = CustomerDebt::where('customer_id', $request->customer_id)
                    ->whereIn('status', ['pending', 'partial'])
                    ->orderBy('transaction_date', 'asc')
                    ->lockForUpdate()
                    ->get();

                $paymentModel = CustomerDebtPayment::class;
                $foreignKey = 'customer_debt_id';
                $cashCategory = 4;
                $label = 'Client #' . $request->customer_id;

                $buyerName = Customer::find($request->customer_id)?->name;
            }

            if ($debts->isEmpty()) {
                return response()->json([
                    'status' => 404,
                    'success' => false,
                    'message' => 'Aucune dette à payer.'
                ], 404);
            }

            foreach ($debts as $debt) {

                if ($remainingAmount <= 0) break;

                $debtRemaining = $debt->loan_amount - $debt->paid_amount;
                if ($debtRemaining <= 0) continue;

                $payAmount = min($remainingAmount, $debtRemaining);

                $paymentModel::create([
                    $foreignKey => $debt->id,
                    'paid_amount' => $payAmount,
                    'cash_account_id' => $request->account_id,
                    'addedBy' => Auth::id(),
                    'operation_date' => $request->transaction_date ?? now(),
                ]);

                $debt->paid_amount += $payAmount;
                $debt->status = $debt->paid_amount >= $debt->loan_amount ? 'paid' : 'partial';
                $debt->save();

                if ($debt->sale_id) {
                    $sale = Sale::lockForUpdate()->find($debt->sale_id);

                    if ($sale) {
                        $sale->paid_amount += $payAmount;
                        $sale->status = $sale->paid_amount >= $sale->total_amount ? 'paid' : 'partial';
                        $sale->save();
                    }
                }

                $currentSolde += $payAmount;

                CashTransaction::create([
                    'reason' => "Paiement dette {$label}",
                    'type' => 'Revenue',
                    'amount' => $payAmount,
                    'transaction_date' => $request->transaction_date ?? now(),
                    'solde' => $currentSolde,
                    'reference' => 'DEBT-' . $debt->id,
                    'reference_id' => $debt->id,
                    'cash_account_id' => $request->account_id,
                    'cash_categorie_id' => $cashCategory,
                    'addedBy' => Auth::id()
                ]);

                $remainingAmount -= $payAmount;
                $totalPaid += $payAmount;
            }

            DB::commit();

            $data = [
                'buyer_name' => $buyerName,
                'payer_type' => $request->distributor_id ? 'distributor' : 'customer',
                'payer_id' => $request->distributor_id ?? $request->customer_id,
                'total_paid' => $totalPaid,
                'remaining_unallocated' => $remainingAmount,
                'new_balance' => $currentSolde,
                'transaction_date' => $request->transaction_date ?? now(),
            ];

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Paiement effectué.',
                'data' => $data,
                'info_company' => $about,
                'devise' => $devise
            ], 200);
        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du paiement.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
    // public function paymentDebt(Request $request)
    // {
    //     $about = About::first();
    //     if ($about) {
    //         $this->imageService->transform($about, ['logo', 'logo2']);
    //     }
    //     $devise = Currency::where('status', 'created')
    //         ->orderByRaw("currency_type = 'devise_principale' DESC")
    //         ->latest()
    //         ->get();
    //     $request->validate([
    //         'distributor_id' => 'nullable|exists:distributors,id',
    //         'customer_id' => 'nullable|exists:customers,id',
    //         'paid_amount' => 'nullable|numeric|min:0.01',
    //         'account_id' => 'nullable|exists:cash_accounts,id',
    //         'transaction_date' => 'nullable|date',
    //         'due_anount' => 'nullable|numeric|min:0.01',
    //     ]);

    //     if (!$request->distributor_id && !$request->customer_id) {
    //         return response()->json([
    //             'status' => 422,
    //             'success' => false,
    //             'message' => 'Veuillez fournir un distributeur ou un client.'
    //         ], 422);
    //     }

    //     if ($request->distributor_id && $request->customer_id) {
    //         return response()->json([
    //             'status' => 422,
    //             'success' => false,
    //             'message' => 'Choisir soit distributeur soit client, pas les deux.'
    //         ], 422);
    //     }

    //     try {
    //         DB::beginTransaction();

    //         $remainingAmount = $request->paid_amount;
    //         $totalPaid = 0;

    //         $lastTransaction = CashTransaction::where('cash_account_id', $request->account_id)
    //             ->latest('id')
    //             ->first();

    //         $currentSolde = $lastTransaction ? $lastTransaction->solde : 0;

    //         if ($request->distributor_id) {

    //             $debts = DebtDistributor::where('distributor_id', $request->distributor_id)
    //                 ->whereIn('status', ['pending', 'partial'])
    //                 ->orderBy('transaction_date', 'asc')
    //                 ->lockForUpdate()
    //                 ->get();

    //             $paymentModel = PaymentDistributor::class;
    //             $foreignKey = 'debt_distributor_id';
    //             $cashCategory = 5;
    //             $label = 'Distributeur #' . $request->distributor_id;
    //         } else {

    //             $debts = CustomerDebt::where('customer_id', $request->customer_id)
    //                 ->whereIn('status', ['pending', 'partial'])
    //                 ->orderBy('transaction_date', 'asc')
    //                 ->lockForUpdate()
    //                 ->get();

    //             $paymentModel = CustomerDebtPayment::class;
    //             $foreignKey = 'customer_debt_id';
    //             $cashCategory = 4;
    //             $label = 'Client #' . $request->customer_id;
    //         }

    //         if ($debts->isEmpty()) {
    //             return response()->json([
    //                 'status' => 422,
    //                 'success' => false,
    //                 'message' => 'Aucune dette à payer.'
    //             ], 404);
    //         }

    //         foreach ($debts as $debt) {

    //             if ($remainingAmount <= 0) break;

    //             $debtRemaining = $debt->loan_amount - $debt->paid_amount;
    //             if ($debtRemaining <= 0) continue;

    //             $payAmount = min($remainingAmount, $debtRemaining);

    //             $paymentModel::create([
    //                 $foreignKey => $debt->id,
    //                 'paid_amount' => $payAmount,
    //                 'cash_account_id' => $request->account_id,
    //                 'addedBy' => Auth::id(),
    //                 'operation_date' => $request->transaction_date ?? now(),
    //             ]);

    //             $debt->paid_amount += $payAmount;

    //             if ($debt->paid_amount >= $debt->loan_amount) {
    //                 $debt->status = 'paid';
    //             } else {
    //                 $debt->status = 'partial';
    //             }

    //             $debt->save();
    //             if (!empty($debt->sale_id)) {

    //                 $sale = Sale::lockForUpdate()->find($debt->sale_id);

    //                 if ($sale) {
    //                     $sale->paid_amount += $payAmount;

    //                     if ($sale->paid_amount >= $sale->total_amount) {
    //                         $sale->status = 'paid';
    //                     } else {
    //                         $sale->status = 'partial';
    //                     }

    //                     $sale->save();
    //                 }
    //             }

    //             $currentSolde += $payAmount;

    //             CashTransaction::create([
    //                 'reason' => "Paiement dette {$label}",
    //                 'type' => 'Revenue',
    //                 'amount' => $payAmount,
    //                 'transaction_date' => $request->transaction_date ?? now(),
    //                 'solde' => $currentSolde,
    //                 'reference' => 'DEBT-' . $debt->id,
    //                 'reference_id' => $debt->id,
    //                 'cash_account_id' => $request->account_id,
    //                 'cash_categorie_id' => $cashCategory,
    //                 'addedBy' => Auth::id()
    //             ]);

    //             $remainingAmount -= $payAmount;
    //             $totalPaid += $payAmount;
    //         }

    //         DB::commit();

    //         return response()->json([
    //             'success' => true,
    //             'status' => 200,
    //             'message' => 'Paiement effectué.',
    //             'info_company' => $about,
    //             'devise' => $devise,
    //             'total_paid' => $totalPaid,
    //             'remaining_unallocated' => $remainingAmount,
    //             'new_balance' => $currentSolde
    //         ]);
    //     } catch (\Throwable $e) {

    //         DB::rollBack();

    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Erreur lors du paiement.',
    //             'error' => config('app.debug') ? $e->getMessage() : null
    //         ], 500);
    //     }
    // }
}
