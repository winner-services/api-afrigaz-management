<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'branch_id',
    'tank_id',
    'total_gas_used',
    'note',
    'addedBy'
])]
class Filling extends Model
{
    //
}
