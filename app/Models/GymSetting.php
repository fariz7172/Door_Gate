<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class GymSetting extends Model {
    protected $fillable = ['gym_name','api_base_url','api_key','relay_mode','relay_ip','default_device_id'];
    protected $casts = ['created_at'=>'datetime','updated_at'=>'datetime'];
    public static function current(): self {
        return static::first() ?? static::create([
            'gym_name' => 'FitQuarters Gym',
            'api_base_url' => config('services.fitquarters.base_url','https://api.fitquarters.id'),
            'api_key' => config('services.fitquarters.key'),
            'relay_mode' => 'dummy',
            'default_device_id' => config('services.fitquarters.default_device_id','front-gate-1'),
        ]);
    }
}
