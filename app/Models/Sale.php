<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['reference', 'branch_id', 'addedBy', 'paid_amount', 'total_amount', 'status', 'customer_id', 'transaction_date', 'sale_type', 'sale_category'])]
class Sale extends Model
{
    public function saleItems()
    {
        return $this->hasMany(ItemSale::class);
    }
    public function items()
    {
        return $this->hasMany(ItemSale::class, 'sale_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'addedBy');
    }
    public function branch()
    {
        return $this->belongsTo(Branche::class, 'branch_id');
    }
}
