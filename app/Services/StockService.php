<?php

namespace App\Services;

use App\Models\StockByBranch;
use App\Models\StockMovement;
use App\Models\Transfer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StockService
{
    /**
     * Ajouter du stock (entrée)
     */
    public static function addStock($branchId, $productId, $quantity, $description = null, $reference = null)
    {
        return DB::transaction(function () use ($branchId, $productId, $quantity, $description, $reference) {

            $stock = StockByBranch::firstOrCreate([
                'branche_id' => $branchId,
                'product_id' => $productId,
            ]);

            $before = $stock->stock_quantity;
            $after = $before + $quantity;

            $stock->update([
                'stock_quantity' => $after
            ]);

            StockMovement::create([
                'branche_id' => $branchId,
                'product_id' => $productId,
                'type' => 'in',
                'quantity' => $quantity,
                'stock_before' => $before,
                'stock_after' => $after,
                'description' => $description,
                'reference_id' => $reference['id'] ?? null,
                'reference' => $reference['type'] ?? null,
                'user_id' => Auth::id(),
            ]);

            return $stock;
        });
    }

    /**
     * Retirer du stock (sortie)
     */
    public static function removeStock($branchId, $productId, $quantity, $description = null, $reference = null)
    {
        return DB::transaction(function () use ($branchId, $productId, $quantity, $description, $reference) {

            $stock = StockByBranch::where([
                'branche_id' => $branchId,
                'product_id' => $productId,
            ])->firstOrFail();

            if ($stock->stock_quantity < $quantity) {
                throw new \Exception("Stock insuffisant");
            }

            $before = $stock->stock_quantity;
            $after = $before - $quantity;

            $stock->update([
                'stock_quantity' => $after
            ]);

            StockMovement::create([
                'branche_id' => $branchId,
                'product_id' => $productId,
                'type' => 'out',
                'quantity' => $quantity,
                'stock_before' => $before,
                'stock_after' => $after,
                'description' => $description,
                'reference_id' => $stock->id,
                'reference' => $stock->reference ?? null,
                'user_id' => Auth::id(),
            ]);

            return $stock;
        });
    }

    /**
     * Transfert entre branches
     */
    public static function transferStock($fromBranch, $toBranch, $productId, $quantity)
    {
        return DB::transaction(function () use ($fromBranch, $toBranch, $productId, $quantity) {

            self::removeStock($fromBranch, $productId, $quantity, "Transfert sortant", [
                'type' => 'transfer'
            ]);

            self::addStock($toBranch, $productId, $quantity, "Transfert entrant", [
                'type' => 'transfer'
            ]);
        });
    }

    /**
     * Ajustement manuel (inventaire)
     */
    public static function adjustStock($branchId, $productId, $newQuantity, $description = null)
    {
        return DB::transaction(function () use ($branchId, $productId, $newQuantity, $description) {

            $stock = StockByBranch::firstOrCreate([
                'branche_id' => $branchId,
                'product_id' => $productId,
            ]);

            $before = $stock->stock_quantity;
            $after = $newQuantity;

            $stock->update([
                'stock_quantity' => $after
            ]);

            StockMovement::create([
                'branche_id' => $branchId,
                'product_id' => $productId,
                'type' => 'adjustment',
                'quantity' => abs($after - $before),
                'stock_before' => $before,
                'stock_after' => $after,
                'description' => $description,
                'user_id' => Auth::id(),
            ]);

            return $stock;
        });
    }

    public static function transferMultipleProductsWithRecord($fromBranch, $toBranch, $productsQuantities, $transfer_date, $userId)
    {
        return DB::transaction(function () use ($fromBranch, $toBranch, $productsQuantities, $transfer_date, $userId) {

            $reference = 'TRF-' . date('YmdHis');

            $transfer = Transfer::create([
                'from_branch_id' => $fromBranch,
                'to_branch_id' => $toBranch,
                'user_id' => $userId,
                'reference' => $reference,
                'transfer_date' => $transfer_date,
                'status' => 'created'
            ]);

            foreach ($productsQuantities as $item) {
                $productId = $item['product_id'];
                $quantity  = $item['quantity'];

                $transfer->items()->create([
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'transfer_id' => $transfer->id
                ]);

                self::removeStock($fromBranch, $productId, $quantity, "Transfert sortant vers la branche $toBranch", [
                    'type' => 'transfer',
                    'reference_id' => $transfer->id,
                    'user_id' => $userId
                ]);

                self::addStock($toBranch, $productId, $quantity, "Transfert entrant depuis la branche $fromBranch", [
                    'type' => 'transfer',
                    'reference_id' => $transfer->id,
                    'user_id' => $userId
                ]);
            }

            return $transfer;
        });
    }
}
