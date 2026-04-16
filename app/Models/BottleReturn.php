<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'branch_id',
    'agent_id',
    'total_items',
    'note',
    'addedBy',
    'reference',
    'return_date'
])]
class BottleReturn extends Model
{
    public function items()
    {
        return $this->hasMany(BottleReturnItem::class);
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'addedBy');
    }

    public function branch()
    {
        return $this->belongsTo(Branche::class);
    }
}
