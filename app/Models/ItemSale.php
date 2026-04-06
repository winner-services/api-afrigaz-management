<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['sale_id', 'product_id', 'quantity', 'unit_price', 'total_price'])]
class ItemSale extends Model
{
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }
}
