<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequiringBody extends Model
{
    protected $table = 'requiring_bodies';
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [];
    }
}
