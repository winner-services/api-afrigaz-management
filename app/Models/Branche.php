<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

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
        static::created(function ($branche) {

            // Récupérer seulement les IDs (plus rapide)
            $products = Product::pluck('id');

            if ($products->isEmpty()) {
                return; // rien à faire
            }

            $data = $products->map(function ($productId) use ($branche) {
                return [
                    'branche_id' => $branche->id,
                    'product_id' => $productId,
                    'stock_quantity' => 0,
                    'status' => 'created',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })->toArray();

            // Insert rapide + ignore doublons
            StockByBranch::insertOrIgnore($data);
        });
    }
}
