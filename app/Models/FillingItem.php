<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'filling_id',
    'product_id',
    'Number_of_bottles',
    'gas_used'
])]
class FillingItem extends Model
{
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
