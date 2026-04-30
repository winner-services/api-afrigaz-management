<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['quantity', 'supplier_id', 'product_id', 'stock_entries_id', 'unit_price'])]
class ItemsStockEntries extends Model
{
    public function stockEntry()
    {
        return $this->belongsTo(StockEntry::class, 'stock_entries_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
