<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'brand', 'plate_number', 'color', 'reference', 'status', 'addedBy'])]
class Charoit extends Model
{
    public function addedBy()
{
    return $this->belongsTo(User::class, 'addedBy');
}
}
