<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['payment_type', 'reference_id', 'reference', 'distributor_id', 'customer_id', 'cash_account_id', 'branch_id', 'paid_amount', 'payment_method', 'payment_date', 'addedBy', 'status', 'description'])]
class PaymentHistorie extends Model
{
    //
}
