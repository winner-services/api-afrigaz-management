<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['branche_id', 'product_id', 'type', 'quantity', 'stock_before', 'stock_after', 'description', 'addedBy', 'reference_id'])]
class StockMovement extends Model
{
    public function branch()
    {
        return $this->belongsTo(Branche::class, 'branche_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reference()
    {
        return $this->morphTo();
    }
}
