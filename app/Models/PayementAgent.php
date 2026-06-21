<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'total_net_a_verser',
    'total_avances_deduites',
    'reste_a_payer',
    'total_masse_salariale_brute',
    'mois_concerne',
    'addedBy',
    'date_activation',
    'reference'
])]
class PayementAgent extends Model
{
    public function details()
    {
        return $this->hasMany(PayementAgentDetail::class, 'paiement_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'addedBy', 'id');
    }
}
