<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class DeviceGate extends Model {
    protected $table = 'devicegate';
    public $timestamps = true;
    protected $fillable = [
        'name','number','flagstatus','type','sn','ip','nodeid','description',
        'groupid','stat','Lift1','Lift2','Lift3','Lift4','locationid',
        'is_enabled',
    ];
    protected $casts = [
        'number' => 'integer',
        'flagstatus' => 'integer',
        'type' => 'integer',
        'nodeid' => 'integer',
        'groupid' => 'integer',
        'stat' => 'integer',
        'Lift1' => 'integer','Lift2'=>'integer','Lift3'=>'integer','Lift4'=>'integer',
        'locationid'=>'integer',
    ];

    // Scopes
    public function scopeConnected($q){ return $q->where('flagstatus',1); }
    public function scopeDisconnected($q){ return $q->where('flagstatus',0); }

    // Accessors for labels
    public function getTypeLabelAttribute(): string {
        return match((int)$this->type){
            0 => 'Finger',
            50 => 'Face',
            1 => 'Soyal',
            default => 'Unknown ('.($this->type ?? '-').')',
        };
    }
    public function getTypeBadgeAttribute(): string {
        return match((int)$this->type){
            0 => 'bg-amber-500 text-white border-amber-500',
            50 => 'bg-violet-600 text-white border-violet-600',
            1 => 'bg-sky-600 text-white border-sky-600',
            default => 'bg-zinc-100 text-zinc-600 border-zinc-200',
        };
    }
    public function getStatLabelAttribute(): string { return (int)$this->stat===1 ? 'OUT' : 'IN'; }
    public function getFlagLabelAttribute(): string { return (int)$this->flagstatus===1 ? 'Connect' : 'Disconnect'; }
    public function getIsConnectedAttribute(): bool { return (int)$this->flagstatus===1; }

    // 25 Sept 26 — status enable/disable mesin (setdevinfo disable_match)
    // Catatan: raw value dibaca manual via getAttributes() agar tidak ditimpa
    //           oleh accessor getIsEnabledAttribute() (recursion).
    public function getIsEnabledAttribute(): bool { return (int)($this->attributes['is_enabled'] ?? 1)===1; }
    public function getEnableLabelAttribute(): string { return $this->is_enabled ? 'Diaktifkan' : 'Dinonaktifkan'; }

    public function group(){ return $this->belongsTo(DeviceGroup::class, 'groupid', 'id'); }
}
