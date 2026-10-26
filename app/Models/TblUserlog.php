<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TblUserlog extends Model
{
    protected $table = 'tbl_userlog';
    protected $primaryKey = 'REC_INDEX';
    public $incrementing = true;
    public $timestamps = false;

    protected $fillable = [
        'USER_ADDR',
        'TM_EVENT',
        'DEVICESN',
        'IMGPATH',
        'devicetype',
        'card',
        'stat',
        'flag',
        'scan_method',
    ];

    protected $casts = [
        'REC_INDEX' => 'integer',
        'USER_ADDR' => 'integer',
        'TM_EVENT' => 'datetime',
        'stat' => 'integer',
        'devicetype' => 'integer',
        'flag' => 'integer',
    ];

    /**
     * Relasi ke profil pengguna (usersprofile) via USER_ADDR = usersprofile.ID
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(UsersProfile::class, 'USER_ADDR', 'ID');
    }

    /**
     * Relasi ke device gate via DEVICESN = devicegate.sn
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(DeviceGate::class, 'DEVICESN', 'sn');
    }

    /**
     * Label IN / OUT
     */
    public function getStatLabelAttribute(): string
    {
        return (int) $this->stat === 1 ? 'OUT' : 'IN';
    }

    /**
     * Nama pengguna atau fallback ke ID
     */
    public function getUserNameAttribute(): string
    {
        return $this->user?->NAME ?? ('User #' . $this->USER_ADDR);
    }
}
