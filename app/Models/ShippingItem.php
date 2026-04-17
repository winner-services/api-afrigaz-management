<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['shipping_id', 'product_id', 'quantity'])]
class ShippingItem extends Model
{
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function shipping()
    {
        return $this->belongsTo(Shipping::class, 'shipping_id');
    }
}
