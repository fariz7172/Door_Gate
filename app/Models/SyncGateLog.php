<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncGateLog extends Model
{
    protected $table = 'sync_gate_logs';

    protected $fillable = [
        'user_id',
        'employee_id',
        'name',
        'card',
        'sn',
        'endpoint',
        'status',
        'http_status',
        'message',
        'payload_summary',
        'synced_at',
    ];

    protected $casts = [
        'payload_summary' => 'array',
        'synced_at'       => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(UsersProfile::class, 'user_id', 'ID');
    }
}
