<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['reference', 'branch_id', 'user_id', 'total_amount', 'status'])]
class Sale extends Model
{
    public function saleItems()
    {
        return $this->hasMany(ItemSale::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'addedBy');
    }
}
