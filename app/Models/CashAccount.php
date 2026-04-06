<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['designation', 'nature', 'reference', 'branche_id', 'addedBy', 'status'])]
class CashAccount extends Model
{
    //
}
