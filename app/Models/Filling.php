<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'branch_id',
    'tank_id',
    'total_gas_used',
    'note',
    'addedBy',
    'operation_date',
    'reference'
])]
class Filling extends Model
{
    public function items()
    {
        return $this->hasMany(FillingItem::class);
    }

    public function tank()
    {
        return $this->belongsTo(Tank::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branche::class);
    }

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'addedBy');
    }
}
