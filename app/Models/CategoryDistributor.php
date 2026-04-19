<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['designation', 'description', 'status'])]
class CategoryDistributor extends Model
{
    public function distributors()
    {
        return $this->hasMany(Distributor::class, 'category_distributor_id');
    }
    public function caussions()
    {
        return $this->hasMany(Caussion::class, 'category_distributor_id');
    }
}
