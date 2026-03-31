<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
#[Fillable(['denomination', 'details', 'register','national_id','tax_number','phone','address','email','logo'])]
class About extends Model
{
    //
}
