<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['product_id', 'branch_id', 'operation_date', 'type', 'quantity', 'stock_before', 'stock_after', 'reference_type', 'reference_id', 'notes', 'addedBy', 'status'])]
class ProductLedger extends Model
{
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
