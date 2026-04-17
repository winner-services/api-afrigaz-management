<?php

namespace App\Services;

use App\Exceptions\StockException;
use App\Models\BottleReturn;
use App\Models\BottleReturnItem;
use App\Models\Branche;
use App\Models\Product;
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
                'is_empty' => true,
                'condition_state' => 'good'
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
                'addedBy' => Auth::id(),
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
                'addedBy' => Auth::id(),
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
                'addedBy' => Auth::id(),
            ]);
            return $stock;
        });
    }

    public static function transferMultipleProductsWithRecord($fromBranch, $driver, $charoit, $productsQuantities, $transfer_date, $userId)
    {
        return DB::transaction(function () use ($fromBranch, $driver, $charoit, $productsQuantities, $transfer_date, $userId) {

            $productsQuantities = $productsQuantities ?? [];

            if (!is_array($productsQuantities) || count($productsQuantities) === 0) {
                throw new \Exception("Liste des produits invalide ou vide");
            }
            foreach ($productsQuantities as $item) {

                $stock = StockByBranch::where([
                    'branche_id' => $fromBranch,
                    'product_id' => $item['product_id']
                ])->first();

                if (!$stock || $stock->stock_quantity < $item['quantity']) {
                    $errors[] = "Produit ID {$item['product_id']} insuffisant";
                }
            }

            if (!empty($errors)) {
                throw new \Exception(json_encode($errors));
            }

            $reference = 'TRF-' . date('YmdHis');

            $transfer = Transfer::create([
                'from_branch_id' => $fromBranch,
                'driver' => $driver,
                'charoit' => $charoit,
                'addedBy' => $userId,
                'reference' => $reference,
                'transfer_date' => $transfer_date,
                'status' => 'created'
            ]);

            foreach ($productsQuantities as $item) {
                if (!isset($item['product_id'], $item['quantity'])) {
                    throw new \Exception("Format produit invalide");
                }

                $productId = $item['product_id'];
                $quantity  = $item['quantity'];
                $toBranch = $item['to_branch_id'] ?? null;

                $transfer->items()->create([
                    'product_id' => $productId,
                    'to_branch_id' => $toBranch,
                    'quantity' => $quantity,
                    'transfer_id' => $transfer->id
                ]);

                self::removeStock($fromBranch, $productId, $quantity, "Transfert sortant vers la branche $toBranch", [
                    'type' => 'transfer',
                    'reference_id' => $transfer->id,
                    'addedBy' => $userId
                ]);

                // self::addStock($toBranch, $productId, $quantity, "Transfert entrant depuis la branche $fromBranch", [
                //     'type' => 'transfer',
                //     'reference_id' => $transfer->id,
                //     'addedBy' => $userId
                // ]);
            }

            return $transfer;
        });
    }

    public static function returnStock(
        int $branchId,
        int $productId,
        int $quantity,
        ?string $description = null,
        ?array $reference = null
    ): StockByBranch {
        return DB::transaction(function () use ($branchId, $productId, $quantity, $description, $reference): StockByBranch {

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
                'type' => 'return',
                'quantity' => $quantity,
                'stock_before' => $before,
                'stock_after' => $after,
                'description' => $description ?? 'Retour client',
                'reference_id' => $reference['id'] ?? null,
                'reference' => $reference['type'] ?? 'sale_return',
                'addedBy' => Auth::id(),
            ]);

            return $stock;
        });
    }

    public function increaseStock(
        $branchId,
        $productId,
        $qty,
        $isEmpty = null,
        $condition = null
    ) {
        $stock = StockByBranch::firstOrCreate([
            'branche_id' => $branchId,
            'product_id' => $productId,
            'is_empty' => $isEmpty,
            'condition_state' => $condition
        ], [
            'stock_quantity' => 0,
            'status' => 'created'
        ]);

        $stock->increment('stock_quantity', $qty);

        return $stock;
    }

    public function decreaseStock(
        $branchId,
        $productId,
        $qty,
        $isEmpty = null,
        $condition = null
    ) {
        $stock = StockByBranch::where([
            'branche_id' => $branchId,
            'product_id' => $productId,
            'is_empty' => $isEmpty,
            'condition_state' => $condition
        ])->first();

        if (!$stock) {
            throw new \Exception("Stock introuvable");
        }

        if ($stock->stock_quantity < $qty) {
            throw new \Exception("Stock insuffisant");
        }

        $stock->decrement('stock_quantity', $qty);

        return $stock;
    }

    public function checkAllStocksOrFail($branchId, $items)
    {
        $errors = [];
        foreach ($items as $item) {

            $stock = StockByBranch::where([
                'branche_id' => $branchId,
                'product_id' => $item['product_id'],
                'is_empty' => $item['is_empty'] ?? true,
                'condition_state' => $item['condition_state'] ?? 'good'
            ])->first();

            if (!$stock) {
                throw new \Exception("Stock introuvable pour product ID {$item['product_id']}");
            }

            if ($stock->stock_quantity < $item['Number_of_bottles']) {
                $errors[] = [
                    'product_id' => $item['product_id'],
                    'message' => 'Stock insuffisant'
                ];
            }
        }
        if (!empty($errors)) {
            throw new \Exception(json_encode($errors));
        }

        return true;
    }

    public function decreaseStockMultiple($branchId, array $products, $isEmpty = true)
    {
        return DB::transaction(function () use ($branchId, $products, $isEmpty) {

            $grouped = [];
            $errors = [];

            // 🔥 1. REGROUPER correctement
            foreach ($products as $product) {

                $productId = $product['product_id'];

                foreach ($product['returns'] as $item) {

                    $condition = 'good';
                    $qty = $item['quantity'];

                    $key = $productId . '-' . $condition;

                    if (!isset($grouped[$key])) {
                        $grouped[$key] = [
                            'product_id' => $productId,
                            'condition' => $condition,
                            'quantity' => 0
                        ];
                    }

                    $grouped[$key]['quantity'] += $qty;
                }
            }

            foreach ($grouped as $item) {

                $stock = StockByBranch::where([
                    'branche_id' => $branchId,
                    'product_id' => $item['product_id'],
                    'is_empty' => $isEmpty,
                    'condition_state' => $item['condition']
                ])->lockForUpdate()->first();

                if (!$stock || $stock->stock_quantity < $item['quantity']) {

                    $errors[] = [
                        'product_id' => $item['product_id'],
                        'condition_state' => $item['condition'],
                        'requested' => $item['quantity'],
                        'available' => $stock->stock_quantity ?? 0,
                        'message' => 'Stock insuffisant'
                    ];
                }
            }

            if (!empty($errors)) {

                throw new StockException(
                    "Stock insuffisant pour un ou plusieurs produits",
                    $errors
                );
            }

            foreach ($grouped as $item) {

                StockByBranch::where([
                    'branche_id' => $branchId,
                    'product_id' => $item['product_id'],
                    'is_empty' => $isEmpty,
                    'condition_state' => 'good'
                ])->decrement('stock_quantity', $item['quantity']);
            }

            return [
                'success' => true,
                'message' => 'Stock mis à jour avec succès'
            ];
        });
    }

    public function storeReturn(array $data)
    {
        return DB::transaction(function () use ($data) {

            $branchId = $data['branch_id'];
            $agentId = Branche::join('users', 'branches.user_id', '=', 'users.id')
                ->where('branches.id', $branchId)
                ->value('users.id');
            $products = $data['products'];
            $return_date = $data['date_operation'] ?? now();

            $totalItems = 0;

            // 🔥 1. calcul total
            foreach ($products as $product) {
                foreach ($product['returns'] as $item) {
                    $totalItems += $item['quantity'];
                }
            }

            // 🔥 2. créer header
            $return = BottleReturn::create([
                'branch_id' => $branchId,
                'agent_id' => $agentId,
                'total_items' => $totalItems,
                'note' => 'Retour de bouteilles',
                'addedBy' => Auth::id(),
                'return_date' => $return_date,
                'reference' => fake()->unique()->numerify('RET-#####')
            ]);

            // 🔥 3. traiter chaque produit
            foreach ($products as $product) {

                $productId = $product['product_id'];

                foreach ($product['returns'] as $item) {

                    $condition = $item['condition'];
                    $qty = $item['quantity'];

                    if ($qty <= 0) {
                        throw new \Exception("Quantité invalide");
                    }

                    // 🔥 enregistrer détail
                    BottleReturnItem::create([
                        'bottle_return_id' => $return->id,
                        'product_id' => $productId,
                        'condition' => $condition,
                        'quantity' => $qty,
                    ]);

                    // mise à jour stock
                    $this->increaseStock(
                        1,
                        $productId,
                        $qty,
                        true,
                        $condition
                    );

                    $this->decreaseStockMultiple(
                        $branchId,
                        $products,
                    );
                }
            }

            return $return->load('items.product');
        });
    }
}
