<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $table = 'audit_logs';
    protected $guarded = ['id'];
    public $timestamps = false;

    protected function casts(): array
    {
        return ['data' => 'array'];
    }
}
