<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'address', 'email', 'phone', 'zone', 'status','is_deleted', 'addedBy', 'caution_amount', 'operation_date'])]
class Distributor extends Model
{
    public function addedBy()
    {
        return $this->belongsTo(User::class, 'addedBy');
    }
}
