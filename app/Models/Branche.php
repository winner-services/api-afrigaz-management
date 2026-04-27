<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

#[Fillable(['name', 'phone', 'city', 'address', 'user_id', 'addedBy', 'status', 'reference'])]
class Branche extends Model
{
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'addedBy');
    }

    public function scopeSearch($query, $term)
    {
        $term = "%$term%";
        $query->where(function ($query) use ($term) {
            $query->where('branches.name', 'like', $term)
                ->orWhere('branches.phone', 'like', $term)
                ->orWhere('branches.city', 'like', $term)
                ->orWhere('branches.address', 'like', $term)
                ->orWhereHas('user', function ($q2) use ($term) {
                    $q2->where('users.name', 'like', $term);
                });
        });
    }

    protected static function booted()
    {
        static::created(function ($branch) {

            $products = Product::select('id', 'category_id')->get();

            if ($products->isEmpty()) {
                return;
            }

            $now = now();

            $stockData = [];
            $ledgerData = [];

            foreach ($products as $product) {

                foreach (Branche::pluck('id') as $branchId) {

                    if ($product->category_id == 2) {

                        $states = [
                            ['is_empty' => true,  'condition_state' => 'good'],
                            ['is_empty' => false, 'condition_state' => 'good'],
                            // ['is_empty' => true,  'condition_state' => 'damaged'],
                            // ['is_empty' => true,  'condition_state' => 'repair'],
                        ];

                        foreach ($states as $state) {
                            $stockData[] = [
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

                        $stockData[] = [
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

                    $ledgerData[] = [
                        'product_id' => $product->id,
                        'branch_id' => $branchId,
                        'operation_date' => $now,
                        'type' => 'init',
                        'quantity' => 0,
                        'stock_before' => 0,
                        'stock_after' => 0,
                        'reference_type' => 'branch_init',
                        'reference_id' => $product->id,
                        'notes' => 'Initialisation produit',
                        'addedBy' => request()->user()->id ?? 1,
                        'status' => 'created',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            StockByBranch::insertOrIgnore($stockData);

            ProductLedger::insert($ledgerData);

            CashAccount::create([
                'designation' => 'Cash - ' . $branch->name,
                'nature' => 'Caisse',
                'reference' => 'CA-' . strtoupper(uniqid()),
                'branche_id' => $branch->id,
                'addedBy' => request()->user()->id ?? 1,
                'status' => 'created',
            ]);
        });
    }
}
