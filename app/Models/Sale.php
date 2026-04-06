<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['reference', 'branch_id', 'user_id', 'total_amount', 'status'])]
class Sale extends Model
{
    //
}
