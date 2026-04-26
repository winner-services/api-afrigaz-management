<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\Bonus;
use App\Models\Bonuse;
use App\Models\CashTransaction;
use App\Models\Payout;
use App\Models\Referral;
use App\Models\ReferralReward;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReferralService
{
    public function handle(Sale $sale)
    {
        $referral = Referral::where('referred_id', $sale->customer_id)->first();

        if (!$referral) {
            return null;
        }

        $exists = ReferralReward::where('sale_id', $sale->id)->exists();

        if ($exists) {
            return null;
        }

        $bonuses = Bonuse::whereIn(
            'product_id',
            $sale->items->pluck('product_id')->unique()
        )
            ->where('is_active', true)
            ->get()
            ->keyBy('product_id');

        $totalReward = 0;

        foreach ($sale->items as $item) {

            $bonus = $bonuses[$item->product_id] ?? null;

            if ($bonus) {
                $totalReward += $bonus->reward_amount * $item->quantity;
                $totalReward += $bonus->reward_amount;
            }
        }

        if ($totalReward <= 0) {
            return null;
        }

        return ReferralReward::create([
            'customer_id' => $referral->referrer_id,
            'referral_id' => $referral->id,
            'sale_id' => $sale->id,
            'transaction_date' => Carbon::now(),
            'amount' => $totalReward,
            'status' => 'pending',
            'addedBy' => $sale->addedBy ?? null,
        ]);
    }

    public function payCustomer($data)
    {
        $customerId = $data['customer_id'];
        $operation_date = $data['operation_date'];
        $account_id = $data['account_id'];

        return DB::transaction(function () use ($customerId, $operation_date, $account_id) {

            $query = ReferralReward::where('customer_id', $customerId)
                ->where('status', 'pending')
                ->lockForUpdate();

            $total = $query->sum('amount');

            if ($total <= 0) {
                throw new \Exception('Aucune commission');
            }

            $query->update([
                'status' => 'paid'
            ]);

            $lastTransaction = CashTransaction::where('cash_account_id', $account_id)
                ->latest('id')
                ->first();

            $solde = $lastTransaction ? $lastTransaction->solde : 0;

            if ($solde < $total) {
                throw new \Exception('Solde insuffisant.');
            }

            $payout = Payout::create([
                'customer_id' => $customerId,
                'amount' => $total,
                'status' => 'paid',
                'paid_at' => $operation_date ?? now(),
                'addedBy' => Auth::id(),
            ]);

            ReferralReward::where('customer_id', $customerId)
                ->where('status', 'pending')
                ->update([
                    'status' => 'paid',
                ]);

            CashTransaction::create([
                'reason' => 'Paiement Bonus du Client',
                'type' => 'Depense',
                'amount' => $total,
                'transaction_date' => $operation_date ?? now(),
                'solde' => $solde - $total,
                'reference_id' => $payout->id,
                'cash_account_id' => $account_id,
                'cash_categorie_id' => 6,
                'addedBy' => Auth::id(),
            ]);

            return [
                'success' => true,
                'status' => 200,
                'message' => 'Paiement effectué',
                'payout' => $payout
            ];
        });
    }
}
