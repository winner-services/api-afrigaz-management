<?php

namespace App\Http\Controllers\Api\Customer\DebtPayment;

use App\Http\Controllers\Controller;
use App\Models\CashTransaction;
use App\Models\Customer;
use App\Models\CustomerDebt;
use App\Models\CustomerDebtPayment;
use App\Models\DebtDistributor;
use App\Models\PaymentDistributor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class CustomerDebtPaymentController extends Controller
{

    #[OA\Post(
        path: '/api/v1/paymentDebtStoreData',
        summary: 'Créer',
        tags: ['customers Debts'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['customer_id', 'paid_amount', 'account_id', 'operation_date'],
                properties: [
                    new OA\Property(property: "customer_id", type: "integer", example: 1),
                    new OA\Property(property: "paid_amount", type: "number", format: "float", example: 100.00),
                    new OA\Property(property: "account_id", type: "integer", example: 1),
                    new OA\Property(property: "operation_date", type: "string", format: "date", example: "2023-01-01"),
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
    public function payDebt(Request $request)
    {
        $request->validate([
            'distributor_id' => 'nullable|exists:distributors,id',
            'customer_id' => 'nullable|exists:customers,id',
            'paid_amount' => 'nullable|numeric|min:0.01',
            'account_id' => 'nullable|exists:cash_accounts,id',
            'transaction_date' => 'nullable|date',
            'due_anount' => 'nullable|numeric|min:0.01',
        ]);

        // 🔥 règle : un seul doit être présent
        if (!$request->distributor_id && !$request->customer_id) {
            return response()->json([
                'success' => false,
                'message' => 'Veuillez fournir un distributeur ou un client.'
            ], 422);
        }

        if ($request->distributor_id && $request->customer_id) {
            return response()->json([
                'success' => false,
                'message' => 'Choisir soit distributeur soit client, pas les deux.'
            ], 422);
        }

        try {
            DB::beginTransaction();

            $remainingAmount = $request->paid_amount;
            $totalPaid = 0;

            $lastTransaction = CashTransaction::where('cash_account_id', $request->account_id)
                ->latest('id')
                ->first();

            $currentSolde = $lastTransaction ? $lastTransaction->solde : 0;

            if ($request->distributor_id) {

                $debts = DebtDistributor::where('distributor_id', $request->distributor_id)
                    ->whereIn('status', ['pending', 'partial'])
                    ->orderBy('transaction_date', 'asc')
                    ->lockForUpdate()
                    ->get();

                $paymentModel = PaymentDistributor::class;
                $foreignKey = 'debt_distributor_id';
                $cashCategory = 4;
                $label = 'Distributeur #' . $request->distributor_id;
            } else {

                $debts = CustomerDebt::where('customer_id', $request->customer_id)
                    ->whereIn('status', ['pending', 'partial'])
                    ->orderBy('transaction_date', 'asc')
                    ->lockForUpdate()
                    ->get();

                $paymentModel = CustomerDebtPayment::class;
                $foreignKey = 'customer_debt_id';
                $cashCategory = 3;
                $label = 'Client #' . $request->customer_id;
            }

            if ($debts->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucune dette à payer.'
                ], 404);
            }

            foreach ($debts as $debt) {

                if ($remainingAmount <= 0) break;

                $debtRemaining = $debt->loan_amount - $debt->paid_amount;
                if ($debtRemaining <= 0) continue;

                $payAmount = min($remainingAmount, $debtRemaining);

                // 🔥 CREATE PAYMENT
                $paymentModel::create([
                    $foreignKey => $debt->id,
                    'paid_amount' => $payAmount,
                    'cash_account_id' => $request->account_id,
                    'addedBy' => Auth::id(),
                    'operation_date' => $request->operation_date ?? now(),
                ]);

                // 🔥 UPDATE DEBT
                $debt->paid_amount += $payAmount;

                if ($debt->paid_amount >= $debt->loan_amount) {
                    $debt->status = 'paid';
                } else {
                    $debt->status = 'partial';
                }

                $debt->save();

                // 🔥 CASH
                $currentSolde += $payAmount;

                CashTransaction::create([
                    'reason' => "Paiement dette {$label}",
                    'type' => 'Revenue',
                    'amount' => $payAmount,
                    'transaction_date' => now(),
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

            return response()->json([
                'success' => true,
                'message' => 'Paiement effectué.',
                'total_paid' => $totalPaid,
                'remaining_unallocated' => $remainingAmount,
                'new_balance' => $currentSolde
            ]);
        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du paiement.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    #[OA\Get(
        path: "/api/v1/customerDebtsGetAllData",
        summary: "Lister",
        tags: ["customers Debts"],
        responses: [
            new OA\Response(response: 200, description: "Liste")
        ]
    )]

    public function customersWithDebts()
    {
        $customers = Customer::whereHas('debts', function ($query) {
            $query->whereIn('status', ['pending', 'partial']);
        })
            ->with(['debts' => function ($query) {
                $query->whereIn('status', ['pending', 'partial'])
                    ->orderBy('transaction_date', 'asc');
            }])
            ->get()
            ->map(function ($customer) {

                $totalDebt = $customer->debts->sum('loan_amount');
                $totalPaid = $customer->debts->sum('paid_amount');

                return [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'total_debt' => $totalDebt,
                    'total_paid' => $totalPaid,
                    'remaining' => $totalDebt - $totalPaid,
                    'debts' => $customer->debts
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $customers
        ]);
    }

    #[OA\Get(
        path: "/api/v1/getAllPayments",
        summary: "Lister",
        tags: ["payment Debts customers"],
        responses: [
            new OA\Response(response: 200, description: "Liste")
        ]
    )]
    public function getAllPayments()
    {
        $payments = CustomerDebtPayment::with([
            'debt.customer',
            'cashAccount',
            'user'
        ])
            ->latest()
            ->paginate(10);

        // ✅ Format propre
        $data = $payments->getCollection()->map(function ($payment) {
            return [
                'id' => $payment->id,
                'amount' => $payment->paid_amount,
                'status' => $payment->status,
                'date' => $payment->operation_date,

                'customer' => [
                    'id' => $payment->debt?->customer?->id,
                    'name' => $payment->debt?->customer?->name,
                ],

                'debt' => [
                    'id' => $payment->debt?->id,
                    'total' => $payment->debt?->loan_amount,
                    'paid' => $payment->debt?->paid_amount,
                ],

                'cash_account' => [
                    'id' => $payment->cashAccount?->id,
                    'name' => $payment->cashAccount?->name,
                ],

                'created_by' => [
                    'id' => $payment->user?->id,
                    'name' => $payment->user?->name,
                ]
            ];
        });

        return response()->json([
            'success' => 200,
            'data' => $data,
        ]);
    }
}
