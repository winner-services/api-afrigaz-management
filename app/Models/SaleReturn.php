<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['sale_id', 'user_id', 'reason'])]
class SaleReturn extends Model
{
    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    // Relation vers l'utilisateur qui a effectué le retour
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(ItemSaleReturn::class, 'sale_return_id');
    }
}
