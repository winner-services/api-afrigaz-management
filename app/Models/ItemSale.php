<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['sale_id', 'product_id', 'quantity', 'unit_price', 'total_price'])]
class ItemSale extends Model
{
    //
}
