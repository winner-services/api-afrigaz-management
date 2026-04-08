<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['supplier_id', 'invoice_number', 'quantity_kg', 'unit_price_per_kg', 'total_cost', 'status', 'addedBy', 'purchase_date', 'lost_Quantity_kg'])]
class Bulk_Purchase extends Model
{
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'addedBy');
    }
}
