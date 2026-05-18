<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['order_id', 'product_id', 'quantity', 'unit_price', 'subtotal', 'delivered_quantity'])]
class OrderItem extends Model
{
    //
}
