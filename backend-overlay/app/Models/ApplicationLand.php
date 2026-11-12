<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationLand extends Model
{
    protected $table = 'application_lands';
    protected $guarded = ['id'];
    public function owners() { return $this->hasMany(LandOwner::class, 'land_id'); }
    public function upazila() { return $this->belongsTo(Upazila::class); }
    public function mouza() { return $this->belongsTo(Mouza::class); }
    public function recordClass() { return $this->belongsTo(LandClass::class, 'record_class_id'); }
    public function usageClass() { return $this->belongsTo(LandClass::class, 'usage_class_id'); }

    protected function casts(): array
    {
        return [];
    }
}
