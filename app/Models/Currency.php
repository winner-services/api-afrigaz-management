<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['designation', 'symbol', 'currency_type', 'conversion_amount', 'status', 'addedBy'])]
class Currency extends Model
{
    //
}
