<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UsersProfile extends Model {
    use HasFactory;

    protected $table = 'usersprofile';
    protected $primaryKey = 'ID';
    public $incrementing = true;
    protected $keyType = 'int';

    // tabel lama (legacy PCL/Tasoft) — updated_at di-handle MySQL ON UPDATE
    public $timestamps = false;

    protected $fillable = [
        'EMPLOYEE_ID','NAME','LAST_NAME','BEGIN_DATE','END_DATE',
        'Depid','Branchid','photo','api_last_update','Card','NoIdentitas',
        'user_type','is_active','JOB_POSITION','BIRTHDAY','is_leave','is_freeze',
        'created_at','updated_at',
    ];

    protected $casts = [
        'ID'          => 'integer',
        'BEGIN_DATE'  => 'datetime',
        'END_DATE'    => 'datetime',
        'BIRTHDAY'    => 'date',
        'Depid'       => 'integer',
        'Branchid'    => 'integer',
        'user_type'   => 'integer',
        'is_active'   => 'integer',
        'is_leave'    => 'integer',
        'is_freeze'   => 'integer',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];

    // kolom BLOB foto — jangan pernah di-select sembarangan
    protected $hidden = ['photo'];

    // ================= RELATIONS =================
    public function branch(){ return $this->belongsTo(Branch::class,'Branchid','id'); }
    public function departemen(){ return $this->belongsTo(Departemen::class,'Depid','id'); }
    public function devices(){ return $this->hasMany(UsersXDevice::class,'userId','ID'); }

    // ================= HELPERS =================
    public function getFullNameAttribute(): string {
        return trim(($this->NAME ?? '').' '.($this->LAST_NAME ?? ''));
    }

    public function getIsActiveAttribute($value): bool {
        return ((int) $value) === 1;
    }

    /**
     * Validasi kartu/barcode untuk akses pintu (dipakai GateServerCommand & API).
     * @return array{ok:bool, reason:string}
     */
    public function checkAccess(): array {
        if ((int) $this->is_freeze === 1) return ['ok'=>false,'reason'=>'Akun dibekukan (is_freeze)'];
        if ((int) $this->is_leave === 1)  return ['ok'=>false,'reason'=>'Sedang cuti (is_leave)'];
        if ((int) $this->is_active !== 1) return ['ok'=>false,'reason'=>'Tidak aktif'];

        $now = now();
        if ($this->BEGIN_DATE && $now->lt($this->BEGIN_DATE)) return ['ok'=>false,'reason'=>'Belum mulai (BEGIN_DATE)'];
        if ($this->END_DATE && $now->gt($this->END_DATE))     return ['ok'=>false,'reason'=>'Sudah berakhir (END_DATE)'];

        return ['ok'=>true,'reason'=>'OK'];
    }

    protected static function boot(): void {
        parent::boot();

        static::saving(function (Model $m) {
            $card = trim((string)($m->Card ?? ''));
            $nik  = trim((string)($m->NoIdentitas ?? ''));
            if ($card !== '' && $nik !== '' && $card === $nik) {
                throw new \InvalidArgumentException('Nomor Kartu (Card) tidak boleh sama dengan No. Identitas (NoIdentitas).');
            }
        });

        static::creating(function (Model $m) {
            $now = now();
            if (empty($m->created_at)) $m->created_at = $now;
            if (empty($m->api_last_update)) $m->api_last_update = $now->format('Y-m-d H:i:s');
            if (empty($m->BEGIN_DATE)) $m->BEGIN_DATE = $now;
            if (empty($m->END_DATE)) $m->END_DATE = '2079-12-31 23:59:59';
            if (empty($m->Depid)) $m->Depid = 1;
            if (empty($m->Branchid)) $m->Branchid = 1;
            if (!isset($m->is_active) || $m->is_active === null) $m->is_active = 1;
            if (!isset($m->user_type) || $m->user_type === null) $m->user_type = 2;
            if (!isset($m->is_leave))  $m->is_leave = 0;
            if (!isset($m->is_freeze)) $m->is_freeze = 0;
        });
    }
}
