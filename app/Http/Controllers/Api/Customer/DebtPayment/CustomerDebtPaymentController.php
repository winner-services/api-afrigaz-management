<?php

namespace App\Http\Controllers\Api\Customer\DebtPayment;

use App\Http\Controllers\Controller;
use App\Models\CashTransaction;
use App\Models\Customer;
use App\Models\CustomerDebt;
use App\Models\CustomerDebtPayment;
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

    public function autoPayDebts(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'paid_amount' => 'required|numeric|min:0.01',
            'account_id' => 'required|exists:cash_accounts,id',
            'operation_date' => 'nullable|date',
        ]);

        try {
            DB::beginTransaction();

            $remainingAmount = $request->paid_amount;
            $totalPaid = 0;

            $lastTransaction = CashTransaction::where('cash_account_id', $request->account_id)
                ->latest('id')
                ->first();
            $currentSolde = $lastTransaction ? $lastTransaction->solde : 0;

            $debts = CustomerDebt::where('customer_id', $request->customer_id)
                ->whereIn('status', ['pending', 'partial'])
                ->orderBy('transaction_date', 'asc')
                ->lockForUpdate()
                ->get();

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

                CustomerDebtPayment::create([
                    'customer_debt_id' => $debt->id,
                    'paid_amount' => $payAmount,
                    'cash_account_id' => $request->account_id,
                    'addedBy' => Auth::id(),
                    'operation_date' => $request->operation_date ?? now(),
                ]);

                // ✅ 2. Mise à jour dette
                $debt->paid_amount += $payAmount;

                if ($debt->paid_amount >= $debt->loan_amount) {
                    $debt->status = 'paid';
                } elseif ($debt->paid_amount > 0) {
                    $debt->status = 'partial';
                }

                $debt->save();

                $currentSolde += $payAmount;

                CashTransaction::create([
                    'reason' => 'Paiement dette client',
                    'type' => 'Revenue',
                    'amount' => $payAmount,
                    'transaction_date' => now(),
                    'solde' => $currentSolde,
                    'reference' => 'DEBT-' . $debt->id,
                    'reference_id' => $debt->id,
                    'cash_account_id' => $request->account_id,
                    'cash_categorie_id' => 3,
                    'addedBy' => Auth::id()
                ]);

                $remainingAmount -= $payAmount;
                $totalPaid += $payAmount;
            }

            DB::commit();

            $updatedDebts = CustomerDebt::where('customer_id', $request->customer_id)
                ->orderBy('transaction_date', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Paiement effectué.',
                'debts' => $updatedDebts,
                'total_paid' => $totalPaid,
                'remaining_unallocated' => $remainingAmount,
                'new_balance' => $currentSolde
            ]);
        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du paiement.',
                'error' => $e->getMessage()
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
