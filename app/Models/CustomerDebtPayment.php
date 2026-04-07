<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['customer_debt_id', 'paid_amount', 'cash_account_id', 'addedBy', 'status'])]
class CustomerDebtPayment extends Model
{
    public function debt()
    {
        return $this->belongsTo(CustomerDebt::class, 'customer_debt_id');
    }

    public function cashAccount()
    {
        return $this->belongsTo(CashAccount::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'addedBy');
    }
}
