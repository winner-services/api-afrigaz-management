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

            $branches = Branche::pluck('id');

            if ($branches->isEmpty()) {
                return;
            }

            $now = now();

            $isBottle = (int) $product->category_id === 2;

            $data = [];

            foreach ($branches as $branchId) {

                if ($isBottle) {

                    $states = [
                        ['is_empty' => true,  'condition_state' => 'good'],
                        ['is_empty' => false, 'condition_state' => 'good'],
                        // ['is_empty' => true,  'condition_state' => 'damaged'],
                        // ['is_empty' => true,  'condition_state' => 'repair'],
                    ];

                    foreach ($states as $state) {
                        $data[] = [
                            'branche_id' => $branchId,
                            'product_id' => $product->id,
                            'categorie_id' => $product->category_id,
                            'stock_quantity' => 0,
                            'is_empty' => $state['is_empty'],
                            'condition_state' => $state['condition_state'],
                            'status' => 'created',
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                } else {
                    $data[] = [
                        'branche_id' => $branchId,
                        'product_id' => $product->id,
                        'categorie_id' => $product->category_id,
                        'stock_quantity' => 0,
                        'is_empty' => false,
                        'condition_state' => 'good',
                        'status' => 'created',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            DB::table('stock_by_branches')->upsert(
                $data,
                [
                    'branche_id',
                    'product_id',
                    'categorie_id',
                    'is_empty',
                    'condition_state'
                ],
                [
                    'stock_quantity',
                    'status',
                    'updated_at'
                ]
            );
            $ledgerData = [];
            foreach ($branches as $branchId) {
                $ledgerData[] = [
                    'product_id' => $product->id,
                    'branch_id' => $branchId,
                    'operation_date' => $now,
                    'type' => 'init',
                    'quantity' => 0,
                    'stock_before' => 0,
                    'stock_after' => 0,
                    'reference_type' => 'product_init',
                    'reference_id' => $product->id,
                    'notes' => 'Initialisation produit',
                    'addedBy' => request()->user()->id ?? 1,
                    'status' => 'created',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            ProductLedger::insert($ledgerData);
        });
    }
}
