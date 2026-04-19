<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['reason', 'type', 'amount', 'transaction_date', 'solde', 'reference', 'reference_id', 'status', 'cash_account_id', 'cash_categorie_id', 'addedBy'])]
class CashTransaction extends Model
{
    public function account()
{
    return $this->belongsTo(CashAccount::class, 'cash_account_id');
}
}
