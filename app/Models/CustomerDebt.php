<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['customer_id', 'sale_id', 'amount', 'loan_amount', 'paid_amount', 'transaction_date', 'motif', 'status', 'user_id'])]
class CustomerDebt extends Model
{
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
