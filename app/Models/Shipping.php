<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['reference', 'branch_id', 'addedBy', 'distributor_id', 'status', 'transaction_date', 'commentaire','planned_date','caussion_id'])]
class Shipping extends Model
{
    public function items()
    {
        return $this->hasMany(ShippingItem::class, 'shipping_id');
    }

    public function distributor()
    {
        return $this->belongsTo(Distributor::class);
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
