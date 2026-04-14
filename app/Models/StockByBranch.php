<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['branche_id', 'product_id', 'stock_quantity', 'status', 'is_empty', 'condition_state'])]
class StockByBranch extends Model
{
    public function branch()
    {
        return $this->belongsTo(Branche::class, 'branche_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
