<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['product_id', 'reward_amount', 'is_active', 'addedBy'])]
class Bonuse extends Model
{
    //
}
