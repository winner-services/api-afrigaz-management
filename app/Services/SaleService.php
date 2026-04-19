<?php

namespace App\Services;

use App\Models\CashTransaction;
use App\Models\CustomerDebt;
use App\Models\DebtDistributor;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Shipping;
use App\Models\ShippingItem;
use App\Models\StockByBranch;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SaleService
{
    public static function createSaleWithPayment($branchId, $products, $userId, $customerId = null, $distributor_id = null, $paidAmount = 0, $account_id, $sale_type, $sale_category)
    {
        return DB::transaction(function () use ($branchId, $products, $userId, $customerId, $distributor_id, $paidAmount, $account_id, $sale_type, $sale_category) {
            $errors = [];

            foreach ($products as $item) {

                $stock = StockByBranch::where([
                    'branche_id' => $branchId,
                    'product_id' => $item['product_id'],
                    'is_empty' => false
                ])->lockForUpdate()->first();

                if (!$stock || $stock->stock_quantity < $item['quantity']) {
                    $errors[] = [
                        'product_id' => $item['product_id'],
                        'message' => 'Stock insuffisant',
                        'available' => $stock->stock_quantity ?? 0
                    ];
                }
            }

            if (!empty($errors)) {
                throw new StockException($errors);
            }

            $reference = 'SALE-' . date('YmdHis');

            $sale = Sale::create([
                'reference' => $reference,
                'branch_id' => $branchId,
                'addedBy' => $userId,
                'total_amount' => 0,
                'paid_amount' => 0,
                'transaction_date' => now(),
                'customer_id' => $customerId,
                'distributor_id' => $distributor_id,
                'sale_type' => $sale_type,
                'sale_category' => $sale_category,
            ]);

            $total = 0;

            foreach ($products as $item) {

                $product = Product::findOrFail($item['product_id']);
                $quantity = $item['quantity'];

                $lineTotal = $quantity * $item['unit_price'];

                $sale->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $item['unit_price'],
                    'total_price' => $lineTotal
                ]);

                StockService::removeStock(
                    $branchId,
                    $product->id,
                    $quantity,
                    "Vente PROD-{$product->id}",
                    [
                        'id' => $sale->id,
                        'type' => 'sale'
                    ]
                );

                StockService::addStock(
                    $branchId,
                    $product->id,
                    $quantity,
                    "Vente PROD-{$product->id}",
                    [
                        'id' => $sale->id,
                        'type' => 'sale'
                    ]
                );

                $total += $lineTotal;
            }

            $sale->update([
                'total_amount' => $total,
                'paid_amount' => $paidAmount,
                'status' => $paidAmount >= $total ? 'completed' : 'pending'
            ]);

            $remaining = $total - $paidAmount;

            $lastTransaction = CashTransaction::where('cash_account_id', $account_id)
                ->latest('id')
                ->first();
            $solde = $lastTransaction ? $lastTransaction->solde : 0;

            if ($paidAmount > 0) {
                CashTransaction::create([
                    'reason' => 'Paiement vente',
                    'type' => 'Revenue',
                    'amount' => $paidAmount,
                    'transaction_date' => now(),
                    'solde' => $solde + $paidAmount,
                    'reference' => $sale->reference,
                    'reference_id' => $sale->id,
                    'cash_account_id' => $account_id,
                    'cash_categorie_id' => 1,
                    'addedBy' => $userId,
                ]);
            }

            if ($remaining > 0 && $customerId) {
                CustomerDebt::create([
                    'customer_id' => $customerId,
                    'sale_id' => $sale->id,
                    'loan_amount' => $total,
                    'paid_amount' => $paidAmount,
                    'transaction_date' => now(),
                    'motif' => 'Vente à crédit',
                    'status' => 'pending',
                    'user_id' => $userId,
                ]);
            }
            if ($remaining > 0 && $distributor_id) {
                DebtDistributor::create([
                    'distributor_id' => $distributor_id,
                    'sale_id' => $sale->id,
                    'loan_amount' => $total,
                    'paid_amount' => $paidAmount,
                    'transaction_date' => now(),
                    'motif' => 'Vente à crédit',
                    'status' => 'pending',
                    'user_id' => $userId,
                ]);
            }

            return $sale;
        });
    }

    public static function deliverShipping($shippingId, $itemsDelivered)
    {
        return DB::transaction(function () use ($shippingId, $itemsDelivered) {

            $shipping = Shipping::with('items')->lockForUpdate()->findOrFail($shippingId);

            $branchId = $shipping->branch_id;

            $errors = [];

            foreach ($itemsDelivered as $data) {

                $item = $shipping->items->firstWhere('id', $data['id']);

                if (!$item) {
                    continue;
                }

                $remainingQty = $item->quantity - $item->delivered_quantity;

                if ($data['delivered_quantity'] > $remainingQty) {
                    $errors[] = [
                        'product_id' => $item->product_id,
                        'message' => 'Quantité dépasse le reste à livrer',
                        'remaining' => $remainingQty
                    ];
                    continue;
                }

                $stock = StockByBranch::where('branche_id', $branchId)
                    ->where('product_id', $item['product_id'])
                    ->where(
                        fn($q) =>
                        $q->where('is_empty', false)
                            ->orWhereNull('is_empty')
                    )
                    ->lockForUpdate()
                    ->first();

                if (!$stock || $stock->stock_quantity < $data['delivered_quantity']) {
                    $errors[] = [
                        'product_id' => $item->product_id,
                        'message' => 'Stock insuffisant',
                        'available' => $stock->stock_quantity ?? 0
                    ];
                }
            }

            if (!empty($errors)) {
                throw new StockException($errors);
            }
            if ($shipping->status === 'completed') {
                throw new \Exception('Livraison déjà terminée');
            }

            foreach ($itemsDelivered as $data) {

                $item = ShippingItem::findOrFail($data['id']);

                if ($data['delivered_quantity'] <= 0) {
                    continue;
                }

                $item->delivered_quantity += $data['delivered_quantity'];
                $item->save();

                StockService::removeStockShippinng(
                    $branchId,
                    $item->product_id,
                    $data['delivered_quantity'],
                    "Livraison SHIP-{$shipping->id}",
                    [
                        'id' => $shipping->id,
                        'type' => 'shipping'
                    ]
                );
            }

            $shipping->load('items');

            $total = $shipping->items->sum('quantity');
            $delivered = $shipping->items->sum('delivered_quantity');

            $status = match (true) {
                $delivered == 0 => 'pending',
                $delivered < $total => 'partial',
                default => 'completed',
            };

            $shipping->update([
                'status' => $status
            ]);

            return [
                'shipping' => $shipping,
                'status' => $status
            ];
        });
    }

    // public static function createShipping($branchId, $products, $distributor_id = null, $transaction_date, $commentaire)
    // {
    //     return DB::transaction(function () use ($branchId, $products, $distributor_id, $transaction_date, $commentaire) {
    //         $errors = [];

    //         foreach ($products as $item) {
    //             $stock = StockByBranch::where('branche_id', $branchId)
    //                 ->where('product_id', $item['product_id'])
    //                 ->where(
    //                     fn($q) =>
    //                     $q->where('is_empty', false)
    //                         ->orWhereNull('is_empty')
    //                 )
    //                 ->lockForUpdate()
    //                 ->first();

    //             if (!$stock || $stock->stock_quantity < $item['quantity']) {
    //                 $errors[] = [
    //                     'product_id' => $item['product_id'],
    //                     'message' => 'Stock insuffisant',
    //                     'available' => $stock->stock_quantity ?? 0
    //                 ];
    //             }
    //         }

    //         if (!empty($errors)) {
    //             throw new StockException($errors);
    //         }

    //         $reference1 = 'LIV-' . date('YmdHis');

    //         $livraision = Shipping::create([
    //             'reference' => $reference1,
    //             'branch_id' => $branchId,
    //             'addedBy' => Auth::id(),
    //             'transaction_date' => $transaction_date ?? now(),
    //             'distributor_id' => $distributor_id,
    //             'commentaire' => $commentaire,
    //         ]);

    //         foreach ($products as $item) {

    //             $product = Product::findOrFail($item['product_id']);
    //             $quantity = $item['quantity'];
    //             $livraision->items()->create([
    //                 'product_id' => $product->id,
    //                 'quantity' => $quantity
    //             ]);

    //             StockService::removeStockShippinng(
    //                 $branchId,
    //                 $product->id,
    //                 $quantity,
    //                 "Livraison PROD-{$product->id}",
    //                 [
    //                     'id' => $livraision->id,
    //                     'type' => 'shipping'
    //                 ]
    //             );
    //         }
    //         return $livraision;
    //     });
    // }
}
