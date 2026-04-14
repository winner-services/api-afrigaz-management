<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
#[Fillable([
    'name',
    'capacity',
    'current_level',
    'status',
    'addedBy'
])]
class Tank extends Model
{
    // public function movements()
    // {
    //     return $this->hasMany(TankMovement::class);
    // }

}
