<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeviceGroup extends Model {
    protected $table = 'devicegroup';
    protected $fillable = ['number','name','description'];

    public function deviceGates(): HasMany {
        return $this->hasMany(DeviceGate::class, 'groupid', 'id');
    }
}
