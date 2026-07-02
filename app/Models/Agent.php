<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name',
    'gender',
    'phone',
    'email',
    'address',
    'fonction_id',
    'status',
    'addedBy',
    'niveau_etude',
    'identity_document',
    'part_number',
    'etat_civil',
])]
class Agent extends Model
{

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'addedBy');
    }

    public function fonction()
    {
        return $this->belongsTo(Fonction::class, 'fonction_id');
    }
    public function paiements()
    {
        return $this->hasMany(PayementAgent::class, 'agent_id');
    }

    public function avances()
    {
        return $this->hasMany(Avance::class, 'agent_id');
    }

    public function avancesDuMois($mois)
    {
        return $this->avances()->where('mois_concerne', $mois)->sum('montant');
    }

    public function totalAvancesDuMois($mois)
    {
        return $this->avances()
            ->where('mois_concerne', $mois)
            ->where('status', 'approuve')
            ->sum('montant');
    }
}
