<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'distributor_id',
    'transaction_date',
    'addedBy',
    'status'
])]
class DetteCylindre extends Model
{
    public function details()
    {
        return $this->hasMany(DetteCylindreDetail::class, 'dette_cylindre_id');
    }
}
