<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Door extends Model {
    protected $fillable = ['door_code','name','direction','relay_channel','pulse_duration_ms','is_active'];
    protected $casts = ['is_active'=>'boolean','relay_channel'=>'integer','pulse_duration_ms'=>'integer'];
    public function accessLogs(): HasMany { return $this->hasMany(AccessLog::class); }
    public function scopeActive($q){ return $q->where('is_active', true); }
}
