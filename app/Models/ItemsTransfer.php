<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['transfer_id', 'to_branch_id', 'product_id', 'quantity', 'status'])]
class ItemsTransfer extends Model
{
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    public function toBranch()
    {
        return $this->belongsTo(Branche::class, 'to_branch_id');
    }
    public function transfer()
    {
        return $this->belongsTo(Transfer::class, 'transfer_id');
    }
    
}
