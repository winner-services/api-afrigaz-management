<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['customer_id', 'referral_id', 'sale_id', 'transaction_date', 'amount', 'status', 'addedBy'])]
class ReferralReward extends Model
{
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function referral()
    {
        return $this->belongsTo(Referral::class);
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }
}
