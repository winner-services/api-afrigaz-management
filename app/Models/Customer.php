<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'email', 'phone', 'address', 'category', 'status', 'addedBy', 'referral_code', 'referred_by'])]
class Customer extends Model
{
    public function debts()
    {
        return $this->hasMany(CustomerDebt::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'addedBy');
    }

    public function referrals()
    {
        return $this->hasMany(Referral::class, 'referrer_id');
    }
}
