<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['type', 'name', 'gender', 'reference', 'rccm', 'idnat', 'manager_name', 'tax_number', 'identity_type', 'password', 'identity_number', 'identity_document', 'phone', 'status', 'email', 'country', 'city', 'commune', 'quartier', 'avenue', 'is_deleted', 'addedBy', 'category_distributor_id'])]
class Distributor extends Model
{
    use HasApiTokens;
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
    public function shippings()
    {
        return $this->hasMany(Shipping::class, 'distributor_id');
    }
}
