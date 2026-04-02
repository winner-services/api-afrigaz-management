<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'phone', 'city', 'address', 'user_id', 'addedBy', 'status'])]
class Branche extends Model
{
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeSearh($query, $term)
    {
        $term = "%$term%";
        $query->where(function ($query) use ($term) {
            $query->where('branches.name', 'like', $term)
                ->orWhere('branches.phone', 'like', $term)
                ->orWhere('branches.city', 'like', $term)
                ->orWhere('branches.address', 'like', $term)
                ->orWhereHas('user', function ($q2) use ($term) {
                    $q2->where('users.name', 'like', $term);
                });
        });
    }
}
