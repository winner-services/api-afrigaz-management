<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'agent_id',
    'account_id',
    'montant',
    'mois_concerne',
    'date_versement',
    'addedBy',
    'status',
    'reference'
])]
class Avance extends Model
{
    public function user()
    {
        return $this->belongsTo(User::class, 'addedBy');
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id');
    }
    public function userAddedBy()
    {
        return $this->belongsTo(User::class, 'addedBy');
    }
}
