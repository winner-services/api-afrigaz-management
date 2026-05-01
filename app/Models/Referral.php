<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['referrer_id', 'referred_id'])]
class Referral extends Model
{
    public function referrals()
    {
        return $this->hasMany(Referral::class, 'referrer_id');
    }

    public function referrer()
    {
        return $this->belongsTo(Customer::class, 'referrer_id');
    }

    public function referred()
    {
        return $this->belongsTo(Customer::class, 'referred_id');
    }

    public function rewards()
    {
        return $this->hasMany(ReferralReward::class);
    }
}
