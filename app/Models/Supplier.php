<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'address', 'phone', 'status', 'addedBy'])]
class Supplier extends Model
{
    //
}
