<?php

namespace App\Services;

use App\Models\Filling;
use App\Models\FillingItem;
use App\Models\Product;
use App\Models\StockByBranch;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FillingService
{
    public function __construct(
        protected TankService $tankService,
        protected StockService $stockService
    ) {}

    public function processFilling(array $data)
    {
        return DB::transaction(function () use ($data) {

            $branchId = 1;

            $tankId = $data['tank_id'];
            $items = $data['items'];
            $operation_date = $data['operation_date'] ?? now()->format('Y-m-d');

            $productIds = collect($items)->pluck('product_id');

            $products = Product::whereIn('id', $productIds)
                ->get()
                ->keyBy('id');

            $totalGas = 0;

            $filling = Filling::create([
                'branch_id' => $branchId,
                'tank_id' => $tankId,
                'total_gas_used' => 0,
                'note' => 'Remplissage du ' . now()->format('Y-m-d H:i:s'),
                'addedBy' => Auth::id(),
                'operation_date' => $operation_date,
                'reference' => fake()->unique()->numerify('FILL-#####')
            ]);

            foreach ($items as $item) {

                $productId = $item['product_id'];
                $qty = (int) $item['Number_of_bottles'];

                if ($qty <= 0) {
                    throw new \Exception("Quantité invalide pour produit ID: $productId");
                }

                if (!isset($products[$productId])) {
                    throw new \Exception("Produit introuvable ID: $productId");
                }

                $product = $products[$productId];

                if ((int) $product->category_id !== 2) {
                    throw new \Exception("Produit ID $productId n'est pas une bouteille");
                }

                if (!$product->weight_kg || $product->weight_kg <= 0) {
                    throw new \Exception("Poids non défini pour produit ID: $productId");
                }

                $emptyStock = StockByBranch::where('branche_id', $branchId)
                    ->where('product_id', $productId)
                    ->where('is_empty', 1)
                    ->where('condition_state', 'good')
                    ->first();

                if (!$emptyStock || $emptyStock->stock_quantity < $qty) {
                    throw new \Exception("Stock insuffisant de bouteilles vides pour produit ID: $productId");
                }

                $gasUsed = $qty * $product->weight_kg;
                $totalGas += $gasUsed;

                $emptyStock->decrement('stock_quantity', $qty);

                $fullStock = StockByBranch::firstOrCreate([
                    'branche_id' => $branchId,
                    'product_id' => $productId,
                    'is_empty' => 0,
                    'condition_state' => 'good'
                ], [
                    'stock_quantity' => 0,
                    'status' => 'created'
                ]);

                $fullStock->increment('stock_quantity', $qty);

                FillingItem::create([
                    'filling_id' => $filling->id,
                    'product_id' => $productId,
                    'Number_of_bottles' => $qty,
                    'gas_used' => $gasUsed,
                ]);
            }

            $filling->update([
                'total_gas_used' => $totalGas
            ]);

            $this->tankService->consumeGas(
                $tankId,
                $totalGas,
                'filling',
                $filling->id,
                $operation_date
            );

            return $filling->load(['items.product']);
        });
    }
}
