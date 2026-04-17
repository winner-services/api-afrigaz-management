<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'distributor_id',
    'sale_id',
    'loan_amount',
    'paid_amount',
    'transaction_date',
    'motif',
    'status',
    'user_id'
])]
class DebtDistributor extends Model
{
    //
}
