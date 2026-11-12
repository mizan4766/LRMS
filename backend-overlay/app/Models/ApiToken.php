<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiToken extends Model
{
    protected $table = 'api_tokens';
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [];
    }
}
