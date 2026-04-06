<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['from_branch_id', 'to_branch_id', 'addedBy', 'reference', 'status', 'transfer_date'])]
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
    public function toBranch()
    {
        return $this->belongsTo(Branche::class, 'to_branch_id');
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
