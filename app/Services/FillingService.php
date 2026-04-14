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
            $tankId = $data['tank_id'];
            $items = $data['items'];

            $products = Product::whereIn(
                'id',
                collect($items)->pluck('product_id')
            )->get()->keyBy('id');

            $totalGas = 0;

            // 🔥 2. validation + calcul gaz total
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
                $weight = $product->weight_kg;

                if (!$weight || $weight <= 0) {
                    throw new \Exception("Poids non défini pour produit ID: $productId");
                }

                // 🔥 vérifier stock AVANT traitement
                $this->stockService->checkStockOrFail(
                    $branchId,
                    $productId,
                    $qty,
                    true,
                    'good'
                );

                $totalGas += $qty * $weight;
            }

            //  créer filling
            $filling = Filling::create([
                'branch_id' => $branchId,
                'tank_id' => $tankId,
                'total_gas_used' => $totalGas,
                'note' => 'Remplissage du ' . now()->format('Y-m-d H:i:s'),
                'addedBy' => Auth::id(),
            ]);

            // consommer gaz citerne
            $this->tankService->consumeGas(
                $tankId,
                $totalGas,
                'filling',
                $filling->id
            );

            // traitement des items
            foreach ($items as $item) {

                $qty = $item['Number_of_bottles'];
                $productId = $item['product_id'];

                $product = $products[$productId];
                $weight = $product->weight_kg;

                // 🔻 retirer bouteilles vides (bon état)
                $this->stockService->decreaseStock(
                    $branchId,
                    $productId,
                    $qty,
                    true,
                    'good'
                );

                // 🔺 ajouter bouteilles pleines
                $this->stockService->increaseStock(
                    $branchId,
                    $productId,
                    $qty,
                    false,
                    null
                );

                // 🔥 enregistrer item
                FillingItem::create([
                    'filling_id' => $filling->id,
                    'product_id' => $productId,
                    'Number_of_bottles' => $qty,
                    'gas_used' => $qty * $weight,
                ]);
            }

            return $filling->load('items');
        });
    }
}
