<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['customer_id', 'amount', 'status', 'paid_at', 'addedBy'])]
class Payout extends Model
{
    //
}
