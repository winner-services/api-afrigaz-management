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
    'user_id',
    'reference'
])]
class DebtDistributor extends Model
{
    public function distributor()
    {
        return $this->belongsTo(Distributor::class);
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function payments()
    {
        return $this->hasMany(
            PaymentDistributor::class
        );
    }
}
