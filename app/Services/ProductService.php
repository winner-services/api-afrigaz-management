<?php

namespace App\Services;

use App\Models\Product;

class ProductService
{
    public function create(array $data)
    {
        return Product::create([
            'name' => $data['name'],
            'reference' => $data['reference'] ?? null,
            'category_id' => $data['category_id'] ?? null,
            'unit_id' => $data['unit_id'] ?? null,
            'type' => $data['type'],
            'weight_kg' => $data['weight_kg'] ?? null,
            'is_returnable' => $data['is_returnable'] ?? false,
            'retail_price' => $data['retail_price'] ?? 0,
            'wholesale_price' => $data['wholesale_price'] ?? 0,
            'manage_stock' => $data['manage_stock'] ?? true,
            'addedBy' => $data['addedBy'] ?? null,
        ]);
    }

    public function updatePrices($id, $retail, $wholesale)
    {
        $product = Product::findOrFail($id);

        $product->update([
            'retail_price' => $retail,
            'wholesale_price' => $wholesale,
        ]);

        return $product;
    }

    public function disable($id)
    {
        return Product::findOrFail($id)->update([
            'status' => 'deleted'
        ]);
    }

    public function listActive()
    {
        return Product::where('status', 'created')->get();
    }
}
