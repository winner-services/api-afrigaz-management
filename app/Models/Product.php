<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
    'is_returnable',
    'manage_stock',
    'minimum_quantity'
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

            $branches = Branche::query()->select('id')->get();

            if ($branches->isEmpty()) {
                return;
            }

            $now = now();

            $isBottle = $product->category_id === 2;

            $data = $branches->map(fn($branche) => [
                'branche_id' => $branche->id,
                'product_id' => $product->id,

                'stock_quantity' => 0,

                'is_empty' => $isBottle ? 1 : null,
                'condition_state' => $isBottle ? 'good' : null,

                'status' => 'created',
                'created_at' => $now,
                'updated_at' => $now,
            ])->toArray();

            DB::table('stock_by_branches')->upsert(
                $data,
                [
                    'branche_id',
                    'product_id',
                    'is_empty',
                    'condition_state'
                ],
                [
                    'stock_quantity',
                    'status',
                    'updated_at'
                ]
            );

            // foreach ($branches as $branche) {

            //     ProductLedger::create([
            //         'product_id' => $product->id,
            //         'branch_id' => $branche->id,
            //         'operation_date' => $now,
            //         'type' => 'init',
            //         'quantity' => 0,

            //         'stock_before' => 0,
            //         'stock_after' => 0,

            //         'reference_type' => 'product_init',
            //         'reference_id' => $product->id,
            //         'notes' => 'Initialisation produit',
            //         'addedBy' => Auth::id(),
            //         'status' => 'created',
            //     ]);
            // }

            foreach ($branches as $branche) {

            ProductLedger::create([
                'product_id' => $product->id,
                'branch_id' => $branche->id,
                'operation_date' => $now,
                'type' => 'init', 
                'quantity' => 0,

                'stock_before' => 0,
                'stock_after' => 0,

                'reference_type' => 'product_init',
                'reference_id' => $product->id,

                'notes' => 'Initialisation produit',
                'addedBy' => Auth::id() ?? 1,
                'status' => 'created',
            ]);
        }
        });
    }
}
