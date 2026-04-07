<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'email', 'phone', 'address', 'category', 'status', 'addedBy'])]
class Customer extends Model
{
    public function debts()
    {
        return $this->hasMany(CustomerDebt::class);
    }
}
