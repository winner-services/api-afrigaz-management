<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['transfer_id', 'product_id', 'quantity'])]
class ItemsTransfer extends Model
{
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    public function transfer()
    {
        return $this->belongsTo(Transfer::class, 'transfer_id');
    }
}
