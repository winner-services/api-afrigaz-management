<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['designation', 'nature', 'reference', 'branche_id', 'addedBy', 'status'])]
class CashAccount extends Model
{
    public function branch()
    {
        return $this->belongsTo(Branche::class, 'branche_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'addedBy');
    }
}
