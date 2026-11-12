<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attachment extends Model
{
    protected $table = 'attachments';
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [];
    }
}
