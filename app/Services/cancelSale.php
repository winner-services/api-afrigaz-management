<?php

namespace App\Services;

use App\Models\CashTransaction;
use App\Models\CustomerDebt;
use App\Models\ItemSale;
use App\Models\ItemSaleReturn;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\StockByBranch;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class cancelSale
{

    public static function cancelSale($saleId, $userId)
    {
        return DB::transaction(function () use ($saleId, $userId) {

            $sale = Sale::with('items')
                ->lockForUpdate()
                ->findOrFail($saleId);

            if ($sale->status === 'cancelled') {
                throw new \Exception("Cette vente est déjà annulée");
            }

            // 🔴 2. Restaurer le stock
            foreach ($sale->items as $item) {

                $stock = StockByBranch::where([
                    'branche_id' => $sale->branch_id,
                    'product_id' => $item->product_id,
                ])
                    ->lockForUpdate()
                    ->firstOrFail();

                $before = $stock->stock_quantity;
                $after = $before + $item->quantity;

                $stock->update([
                    'stock_quantity' => $after
                ]);

                // 🔥 Mouvement inverse
                StockMovement::create([
                    'branche_id' => $sale->branch_id,
                    'product_id' => $item->product_id,
                    'type' => 'cancel',
                    'quantity' => $item->quantity,
                    'stock_before' => $before,
                    'stock_after' => $after,
                    'description' => 'Annulation vente',
                    'reference_id' => $sale->id,
                    'reference' => 'sale_cancel',
                    'user_id' => $userId,
                ]);
            }

            $account = CashTransaction::where([
                'reference' => $sale->reference,
                'reference_id' => $sale->id
            ])->update([
                'status' => 'cancelled'
            ]);

            $lastTransaction = CashTransaction::where('cash_account_id', $account->cash_account_id)
                ->latest('id')
                ->first();
            $solde = $lastTransaction ? $lastTransaction->solde : 0;

            CashTransaction::create([
                'reason' => 'Remboursement vente annulée',
                'type' => 'Depense',
                'amount' => $sale->total_amount,
                'transaction_date' => now(),
                'reference' => $sale->reference,
                'reference_id' => $sale->id,
                'addedBy' => $userId,
                'cash_categorie_id' => 2,
                'cash_account_id' => $account->cash_account_id,
                'solde' => $solde - $sale->total_amount,
            ]);

            CustomerDebt::where('sale_id', $sale->id)
                ->update([
                    'status' => 'cancelled'
                ]);

            $sale->update([
                'status' => 'cancelled'
            ]);

            return $sale;
        });
    }

    public static function returnProductsWithRefund($saleId, $items, $userId, $reason = null)
    {
        return DB::transaction(function () use ($saleId, $items, $userId, $reason) {

            $sale = Sale::with('saleItems', 'customer')->findOrFail($saleId);

            $saleReturn = SaleReturn::create([
                'sale_id' => $sale->id,
                'user_id' => $userId,
                'reason' => $reason
            ]);

            $totalRefund = 0;

            foreach ($items as $item) {
                $saleItem = $sale->saleItems()->where('product_id', $item['product_id'])->firstOrFail();
                // $totalRefund += $saleItem->unit_price * $item['quantity'];
                if ($item['quantity'] > $saleItem->quantity) {
                    throw new \Exception("Quantité de retour supérieure à la vente");
                }
                // Créer item retour
                ItemSaleReturn::create([
                    'sale_return_id' => $saleReturn->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity']
                ]);

                $refundAmount = $saleItem->unit_price * $item['quantity'];
                $totalRefund += $refundAmount;

                StockService::addStock($sale->branch_id, $item['product_id'], $item['quantity'], "Retour produit");

                StockMovement::create([
                    'branche_id' => $sale->branch_id,
                    'product_id' => $item['product_id'],
                    'type' => 'return',
                    'quantity' => $item['quantity'],
                    'stock_before' => StockByBranch::where('branche_id', $sale->branch_id)
                        ->where('product_id', $item['product_id'])
                        ->first()->stock_quantity - $item['quantity'],
                    'stock_after' => StockByBranch::where('branche_id', $sale->branch_id)
                        ->where('product_id', $item['product_id'])
                        ->first()->stock_quantity,
                    'description' => $reason,
                    'user_id' => $userId
                ]);

                $saleItem->decrement('quantity', $item['quantity']);
            }
            $account = CashTransaction::where([
                'reference' => $sale->reference,
                'reference_id' => $sale->id
            ])->update([
                'status' => 'cancelled'
            ]);
            $lastTransaction = CashTransaction::where('cash_account_id', $account->cash_account_id)
                ->latest('id')
                ->first();
            $solde = $lastTransaction ? $lastTransaction->solde : 0;

            if ($totalRefund > 0) {
                CashTransaction::create([
                    'reason' => "Remboursement retour produit - Sale #{$sale->id}",
                    'type' => 'Depense',
                    'amount' => $totalRefund,
                    'transaction_date' => now(),
                    'solde' => $solde - $totalRefund ?? -$totalRefund,
                    'reference' => 'SALE_RETURN',
                    'reference_id' => $saleReturn->id,
                    'status' => 'created',
                    'addedBy' => $userId,
                    'cash_categorie_id' => 2,
                    'cash_account_id' => $account->cash_account_id
                ]);
            }
            if ($sale->customer_id && $sale->paid_amount < $sale->total_amount) {
                $debt = CustomerDebt::where('sale_id', $sale->id)->first();
                if ($debt) {
                    $debt->loan_amount -= $totalRefund;
                    $debt->save();
                }
            }

            return [
                'sale_return' => $saleReturn,
                'total_refund' => $totalRefund
            ];
        });
    }

    public static function returnProducts($saleId, $items, $userId, $reason = null)
    {
        return DB::transaction(function () use ($saleId, $items, $userId, $reason) {

            $sale = Sale::findOrFail($saleId);

            $saleReturn = SaleReturn::create([
                'sale_id' => $sale->id,
                'user_id' => $userId,
                'reason' => $reason
            ]);

            foreach ($items as $item) {
                $saleItem = ItemSale::where([
                    'sale_id' => $sale->id,
                    'product_id' => $item['product_id']
                ])->firstOrFail();

                if ($item['quantity'] > $saleItem->quantity) {
                    throw new \Exception("Quantité de retour supérieure à la vente");
                }

                ItemSaleReturn::create([
                    'sale_return_id' => $saleReturn->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity']
                ]);

                // restaurer stock
                StockService::addStock($sale->branch_id, $item['product_id'], $item['quantity'], "Retour produit");

                // enregistrer mouvement
                StockMovement::create([
                    'branche_id' => $sale->branch_id,
                    'product_id' => $item['product_id'],
                    'type' => 'return',
                    'quantity' => $item['quantity'],
                    'stock_before' => StockByBranch::where('branche_id', $sale->branch_id)
                        ->where('product_id', $item['product_id'])
                        ->first()->stock_quantity - $item['quantity'],
                    'stock_after' => StockByBranch::where('branche_id', $sale->branch_id)
                        ->where('product_id', $item['product_id'])
                        ->first()->stock_quantity,
                    'description' => $reason,
                    'user_id' => $userId
                ]);

                $saleItem->decrement('quantity', $item['quantity']);
            }
            return $saleReturn;
        });
    }
}
