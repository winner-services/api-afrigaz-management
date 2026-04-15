<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['from_branch_id', 'charoit', 'addedBy', 'reference', 'status', 'transfer_date', 'driver'])]
class Transfer extends Model
{
    public function items()
    {
        return $this->hasMany(ItemsTransfer::class, 'transfer_id');
    }

    public function fromBranch()
    {
        return $this->belongsTo(Branche::class, 'from_branch_id');
    }
    public function driver()
    {
        return $this->belongsTo(User::class, 'driver');
    }
    public function charoit()
    {
        return $this->belongsTo(Charoit::class, 'charoit');
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
