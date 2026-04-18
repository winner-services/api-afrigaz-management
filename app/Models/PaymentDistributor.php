<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['debt_distributor_id', 'paid_amount', 'cash_account_id', 'addedBy', 'status', 'operation_date'])]
class PaymentDistributor extends Model
{
    public function debt()
    {
        return $this->belongsTo(DebtDistributor::class, 'debt_distributor_id');
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
