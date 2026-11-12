<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandClass extends Model
{
    protected $table = 'land_classes';
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [];
    }
}
