<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'denomination',
    'rccm',
    'register',
    'national_id',
    'tax_number',
    'phone',
    'address',
    'email',
    'logo',
    'import_export',
    'logo2',
    'opening_time',
    'closing_time',
    'grace_minutes',
    'working_days'
])]

class About extends Model
{
    protected function casts(): array
    {
        return [
            'working_days' => 'array',
        ];
    }
}
