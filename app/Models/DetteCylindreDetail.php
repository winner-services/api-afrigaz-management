<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'dette_cylindre_id',
    'product_id',
    'quantity',
    'date_retour',
    'returned_quantity'
])]
class DetteCylindreDetail extends Model
{
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
