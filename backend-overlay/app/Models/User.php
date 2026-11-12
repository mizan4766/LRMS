<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'users';
    protected $guarded = ['id'];
    protected $hidden = ['password'];
    public function isRole(string ...$r): bool { return in_array($this->role, $r, true); }

    protected function casts(): array
    {
        return ['password' => 'hashed', 'is_active' => 'boolean'];
    }
}
