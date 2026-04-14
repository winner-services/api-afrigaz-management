<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name',
    'capacity',
    'current_level',
    'status',
    'addedBy'
])]
class Tank extends Model
{
    public function user()
    {
        return $this->belongsTo(User::class, 'addedBy');
    }
    
}
