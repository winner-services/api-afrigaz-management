<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'bottle_return_id',
    'product_id',
    'condition',
    'quantity'
])]
class BottleReturnItem extends Model
{
    public function return()
    {
        return $this->belongsTo(BottleReturn::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
