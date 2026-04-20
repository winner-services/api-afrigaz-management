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
}
