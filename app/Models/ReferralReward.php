<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['customer_id', 'referral_id', 'sale_id', 'transaction_date', 'amount', 'status', 'addedBy'])]
class ReferralReward extends Model
{
    //
}
