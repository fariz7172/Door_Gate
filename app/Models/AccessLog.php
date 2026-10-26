<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessLog extends Model {
    protected $fillable = ['door_id','member_id','member_name','scan_type','status','reason','fitquarters_event_id','scanned_at'];
    protected $casts = ['scanned_at'=>'datetime'];
    public function door(): BelongsTo { return $this->belongsTo(Door::class); }
    public function scopeLatestFirst($q){ return $q->orderByDesc('scanned_at'); }
}
