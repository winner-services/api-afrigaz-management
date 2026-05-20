<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['distributor_id', 'reference', 'status', 'total', 'payment_method', 'delivery_address', 'confirmed_by', 'rejected_by', 'note', 'amount', 'order_date'])]
class Order extends Model
{
    //
}
