<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'address', 'email', 'phone', 'zone', 'status', 'is_deleted', 'addedBy', 'caution_amount', 'operation_date', 'category_distributor_id'])]
class Distributor extends Model
{
    public function addedBy()
    {
        return $this->belongsTo(User::class, 'addedBy');
    }
    public function debts()
    {
        return $this->hasMany(DebtDistributor::class);
    }
    public function categoryDistributor()
    {
        return $this->belongsTo(CategoryDistributor::class, 'category_distributor_id');
    }

    public function category()
    {
        return $this->belongsTo(CategoryDistributor::class, 'category_distributor_id');
    }
}
