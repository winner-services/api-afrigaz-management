<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
#[Fillable(['customer_debt_id', 'paid_amount', 'cash_account_id', 'addedBy', 'status'])]
class CustomerDebtPayment extends Model
{
    //
}
