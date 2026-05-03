<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'user_id',
    'requested_at',
    'requested_minutes',
    'reason',
    'status',
    'approved_until',
    'operation_date',
    'approved_by',
    'rejected_by',
    'rejected_at',
    'approved_at'
])]
class OvertimeRequest extends Model
{
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
    public function rejectedBy()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }
}
