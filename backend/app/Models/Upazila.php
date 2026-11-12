<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Upazila extends Model
{
    protected $table = 'upazilas';
    protected $guarded = ['id'];
    public function district() { return $this->belongsTo(District::class); }

    protected function casts(): array
    {
        return [];
    }
}
