<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandOwner extends Model
{
    protected $table = 'land_owners';
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['extra' => 'array', 'mutation_done' => 'boolean'];
    }
}
