<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'dette_detail_id',
    'distributor_id',
    'product_id',
    'returned_quantity',
    'date_retour',
    'addedBy'
])]
class HistoriqueRetourDetteCylindre extends Model
{
    //
}
