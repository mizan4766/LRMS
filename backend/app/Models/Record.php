<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Record extends Model
{
    protected $table = 'records';
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['data' => 'array', 'published_at' => 'datetime'];
    }
}
