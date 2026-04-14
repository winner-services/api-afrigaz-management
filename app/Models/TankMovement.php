<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'tank_id',
    'type',
    'quantity',
    'addedBy',
    'note',
    'reference_type',
    'reference_id',
    'operation_date'
])]
class TankMovement extends Model
{
    public function tank()
    {
        return $this->belongsTo(Tank::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'addedBy');
    }
}
