<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['product_id', 'reward_amount', 'is_active', 'addedBy'])]
class Bonuse extends Model
{
    public function user()
    {
        return $this->belongsTo(User::class, 'addedBy');
    }
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
