<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['transaction_date', 'reference', 'supplier_id', 'addedBy', 'status'])]
class StockEntry extends Model
{

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }


    public function user()
    {
        return $this->belongsTo(User::class, 'addedBy');
    }

    public function items()
    {
        return $this->hasMany(ItemsStockEntries::class, 'stock_entries_id');
    }

    public function movements()
    {
        return $this->morphMany(StockMovement::class, 'reference');
    }
}
