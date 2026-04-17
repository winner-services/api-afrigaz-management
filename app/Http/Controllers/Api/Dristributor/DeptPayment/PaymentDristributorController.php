<?php

namespace App\Http\Controllers\Api\Dristributor\DeptPayment;

use App\Http\Controllers\Controller;
use App\Models\CashTransaction;
use App\Models\DebtDistributor;
use App\Models\Distributor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\JsonResponse;

class PaymentDristributorController extends Controller
{
    public function index(): JsonResponse
    {
        $page = request('paginate', 10);
        $q = request('q', '');
        $sort_direction = request('sort_direction', 'desc');
        $sort_field = request('sort_field', 'id');

        // 🔒 Sécurité tri
        $allowedSortFields = ['id', 'name', 'phone', 'address', 'created_at', 'zone', 'caution_amount', 'operation_date'];

        if (!in_array($sort_field, $allowedSortFields)) {
            $sort_field = 'id';
        }

        if (!in_array(strtolower($sort_direction), ['asc', 'desc'])) {
            $sort_direction = 'desc';
        }

        $data = Distributor::query()
            ->leftJoin('users', 'distributors.addedBy', '=', 'users.id')
            ->select(
                'distributors.*',
                'users.name as addedBy'
            )
            ->where('distributors.is_deleted', false)

            // 🔍 Recherche
            ->when($q, function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('distributors.name', 'LIKE', "%{$q}%")
                        ->orWhere('distributors.phone', 'LIKE', "%{$q}%")
                        ->orWhere('distributors.address', 'LIKE', "%{$q}%")
                        ->orWhere('users.name', 'LIKE', "%{$q}%");
                });
            })

            ->orderBy("distributors.$sort_field", $sort_direction)
            ->paginate($page);

        return response()->json([
            'status' => true,
            'message' => 'succès',
            'data' => $data
        ]);
    }



    public function autoPayDebts(Request $request)
    {
        $request->validate([
            'distributor_id' => 'required|exists:distributors,id',
            'paid_amount' => 'required|numeric|min:0.01',
            'account_id' => 'required|exists:cash_accounts,id',
        ]);

        try {
            DB::beginTransaction();

            $remainingAmount = $request->paid_amount;
            $totalPaid = 0;

            $lastTransaction = CashTransaction::where('cash_account_id', $request->account_id)
                ->latest('id')
                ->first();
            $currentSolde = $lastTransaction ? $lastTransaction->solde : 0;

            $debts = DebtDistributor::where('distributor_id', $request->distributor_id)
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

                DebtDistributor::create([
                    'distributor_id' => $debt->distributor_id,
                    'sale_id' => $debt->sale_id,
                    'paid_amount' => $payAmount,
                    'cash_account_id' => $request->account_id,
                    'addedBy' => Auth::id(),
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
                    'reason' => 'Paiement dette Distributeur #' . $debt->distributor_id,
                    'type' => 'Revenue',
                    'amount' => $payAmount,
                    'transaction_date' => now(),
                    'solde' => $currentSolde,
                    'reference' => 'DEBT-' . $debt->id,
                    'reference_id' => $debt->id,
                    'cash_account_id' => $request->account_id,
                    'cash_categorie_id' => 4,
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
        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du paiement.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
