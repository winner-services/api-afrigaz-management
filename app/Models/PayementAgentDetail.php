<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'paiement_id',
    'agent_id',
    'salaire_base',
    'account_id',
    'total_avances',
    'net_a_payer',
    'date_paiement',
    'status',
    'reference',
    'reference_paiement',
    'type_payment',
    'confirmedBy'
])]
class PayementAgentDetail extends Model
{
    public function compte()
    {
        return $this->belongsTo(CashAccount::class, 'account_id');
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id');
    }
    public function paiementGlobal()
    {
        return $this->belongsTo(PayementAgent::class, 'paiement_id');
    }
}
