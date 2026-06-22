<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'dette_cylindre_id',
    'product_id',
    'quantity',
    'date_retour'
])]
class DetteCylindreDetail extends Model
{
    //
}
