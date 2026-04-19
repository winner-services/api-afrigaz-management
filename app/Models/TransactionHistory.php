<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['from_account_id', 'to_account_id', 'amount', 'type_transaction', 'description', 'addedBy', 'transaction_date'])]
class TransactionHistory extends Model
{
    public function user()
    {
        return $this->belongsTo(User::class, 'addedBy');
    }
    public function from()
    {
        return $this->belongsTo(CashAccount::class, 'from_account_id');
    }
    public function to()
    {
        return $this->belongsTo(CashAccount::class, 'to_account_id');
    }
}
