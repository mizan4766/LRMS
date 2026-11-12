<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Application extends Model
{
    protected $table = 'applications';
    protected $guarded = ['id'];
    public function lands() { return $this->hasMany(ApplicationLand::class); }
    public function attachments() { return $this->hasMany(Attachment::class); }
    public function tasks() { return $this->hasMany(Task::class); }
    public function records() { return $this->hasMany(Record::class); }
    public function body() { return $this->belongsTo(RequiringBody::class, 'requiring_body_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function district() { return $this->belongsTo(District::class); }

    protected function casts(): array
    {
        return ['flags' => 'array', 'has_prior_acquisition' => 'boolean', 'is_npp' => 'boolean', 'claims_open' => 'boolean', 'compensation_open' => 'boolean'];
    }
}
