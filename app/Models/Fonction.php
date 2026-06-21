<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'designation',
    'montant',
    'addedBy',
    'status',
    'reference'
])]
class Fonction extends Model
{
    public function addedBy()
    {
        return $this->belongsTo(User::class, 'addedBy');
    }
    public function agents()
    {
        return $this->hasMany(Agent::class, 'fonction_id');
    }
}
