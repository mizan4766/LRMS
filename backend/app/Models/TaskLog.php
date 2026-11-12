<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskLog extends Model
{
    protected $table = 'task_logs';
    protected $guarded = ['id'];
    public $timestamps = false;

    protected function casts(): array
    {
        return ['data' => 'array'];
    }
}
