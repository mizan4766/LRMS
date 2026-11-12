<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $table = 'tasks';
    protected $guarded = ['id'];
    public function application() { return $this->belongsTo(Application::class); }

    protected function casts(): array
    {
        return ['data' => 'array'];
    }
}
