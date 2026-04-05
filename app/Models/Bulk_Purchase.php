<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
#[Fillable(['supplier_id', 'invoice_number', 'quantity_kg', 'unit_price_per_kg', 'total_cost', 'status', 'addedBy', 'purchase_date'])]
class Bulk_Purchase extends Model
{
    //
}
