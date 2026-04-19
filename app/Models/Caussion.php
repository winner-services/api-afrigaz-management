<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'amount',
    'transaction_date',
    'category_distributor_id',
    'addedBy',
    'status'
])]
class Caussion extends Model
{
    public function items()
    {
        return $this->hasMany(CaussionItem::class);
    }

    public function distributor()
    {
        return $this->belongsTo(CategoryDistributor::class, 'category_distributor_id');
    }
    public function category()
    {
        return $this->belongsTo(CategoryDistributor::class, 'category_distributor_id');
    }
    public function addedBy()
    {
        return $this->belongsTo(User::class, 'addedBy');
    }
}
