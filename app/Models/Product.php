<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name',
    'category_id',
    'weight_kg',
    'wholesale_price',
    'retail_price',
    'status',
    'addedBy',
    'unit_id',
    'reference',
    'type',
    'is_returnable',
    'manage_stock',
])]

class Product extends Model
{
    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'addedBy');
    }

    public function scopeSearch($query, $term)
    {
        $term = "%$term%";
        $query->where(function ($query) use ($term) {
            $query->where('products.name', 'like', $term)
                ->orWhere('products.status', 'like', $term)
                ->orWhere('products.weight_kg', 'like', $term)
                ->orWhere('products.wholesale_price', 'like', $term)
                ->orWhereHas('user', function ($q2) use ($term) {
                    $q2->where('users.name', 'like', $term);
                })
                ->orWhereHas('category', function ($q3) use ($term) {
                    $q3->where('product_categories.designation', 'like', $term);
                })->orWhereHas('unit', function ($q4) use ($term) {
                    $q4->where('units.abreviation', 'like', $term);
                });
        });
    }

    protected static function booted()
    {
        static::created(function ($product) {

            $branches = Branche::pluck('id');

            if ($branches->isEmpty()) {
                return;
            }

            $data = $branches->map(function ($brancheId) use ($product) {
                return [
                    'branche_id' => $brancheId,
                    'product_id' => $product->id,
                    'stock_quantity' => 0,
                    'status' => 'created',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })->toArray();

            StockByBranch::insertOrIgnore($data);
        });
    }
}
