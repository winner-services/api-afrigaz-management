<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'caussion_id',
    'product_id',
    'quantity'
])]
class CaussionItem extends Model
{
    public function caussion()
    {
        return $this->belongsTo(Caussion::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
