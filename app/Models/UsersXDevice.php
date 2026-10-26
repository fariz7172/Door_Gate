<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class UsersXDevice extends Model {
    protected $table = 'usersxdevice';
    public $timestamps = false;
    protected $fillable = ['userId','gateId','Lift1','Lift2','Lift3','Lift4'];
    public function user(){ return $this->belongsTo(UsersProfile::class,'userId','ID'); }
    public function gate(){ return $this->belongsTo(DeviceGate::class,'gateId','id'); }
}
