<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['sale_return_id', 'product_id', 'quantity'])]
class ItemSaleReturn extends Model
{
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function saleReturn()
    {
        return $this->belongsTo(SaleReturn::class);
    }
}
