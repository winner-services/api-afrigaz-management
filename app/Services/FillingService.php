<?php

namespace App\Services;

use App\Models\Filling;
use App\Models\FillingItem;
use App\Models\Product;
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

            if (!$branchId) {
                throw new \Exception("Branche introuvable");
            }

            $tankId = $data['tank_id'];
            $items = $data['items'];
            $operation_date = $data['operation_date'] ?? now()->format('Y-m-d');


            $products = Product::whereIn(
                'id',
                collect($items)->pluck('product_id')
            )->get()->keyBy('id');


            $this->stockService->checkAllStocksOrFail($branchId, $items);

            $totalGas = 0;


            foreach ($items as $item) {

                $qty = $item['Number_of_bottles'];
                $productId = $item['product_id'];

                if ($qty <= 0) {
                    throw new \Exception("Quantité invalide pour produit ID: $productId");
                }

                if (!isset($products[$productId])) {
                    throw new \Exception("Produit introuvable ID: $productId");
                }

                $product = $products[$productId];

                if (!$product->weight_kg || $product->weight_kg <= 0) {
                    throw new \Exception("Poids non défini pour produit ID: $productId");
                }

                $totalGas += $qty * $product->weight_kg;
            }
            $filling = Filling::create([
                'branch_id' => $branchId,
                'tank_id' => $tankId,
                'total_gas_used' => $totalGas,
                'note' => 'Remplissage du ' . now()->format('Y-m-d H:i:s'),
                'addedBy' => Auth::id(),
                'operation_date' => $operation_date,
                'reference' => fake()->unique()->numerify('FILL-#####')
            ]);

            $this->tankService->consumeGas(
                $tankId,
                $totalGas,
                'filling',
                $filling->id,
                $operation_date
            );


            foreach ($items as $item) {

                $qty = $item['Number_of_bottles'];
                $productId = $item['product_id'];

                $product = $products[$productId];


                $this->stockService->decreaseStock(
                    $branchId,
                    $productId,
                    $qty,
                    true,
                    'good'
                );


                $this->stockService->increaseStock(
                    $branchId,
                    $productId,
                    $qty,
                    false,
                    null
                );

                FillingItem::create([
                    'filling_id' => $filling->id,
                    'product_id' => $productId,
                    'Number_of_bottles' => $qty,
                    'gas_used' => $qty * $product->weight_kg,
                ]);
            }

            return $filling->load(['items.product']);
        });
    }
}
