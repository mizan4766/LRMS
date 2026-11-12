<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class District extends Model
{
    protected $table = 'districts';
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [];
    }
}
