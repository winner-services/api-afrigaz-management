<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'company_name', 'email', 'country', 'city', 'tax_number', 'rccm', 'idnat', 'address', 'phone', 'status', 'addedBy'])]
class Supplier extends Model
{
    public function user()
    {
        return $this->belongsTo(User::class, 'addedBy');
    }
}
