<?php
namespace App\Services;
use App\Models\Door;
use App\Models\GymSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DoorService {
    public function pulse(Door $door): bool {
        $setting = GymSetting::current();
        $mode = $setting->relay_mode ?? 'dummy';
        $ip   = $setting->relay_ip;
        $ch   = $door->relay_channel;
        $ms   = $door->pulse_duration_ms;

        if($mode==='dummy'){
            Log::info("DoorService dummy pulse door={$door->door_code} ch={$ch} ms={$ms}");
            return true;
        }
        if($mode==='http'){
            if(empty($ip)){ Log::warning('DoorService http mode but relay_ip empty'); return false; }
            try {
                // generic relay board: GET http://IP/relay/{ch}/on?pulse=ms  or /api/relay
                $url = rtrim($ip,'/');
                if(!str_starts_with($url,'http')) $url = 'http://'.$url;
                // try common endpoint
                $res = Http::timeout(2)->get($url . "/relay/{$ch}/on", ['pulse'=>$ms]);
                if($res->successful()) return true;
                // fallback: /api/relay/open
                $res2 = Http::timeout(2)->post($url . '/api/relay/open', ['channel'=>$ch,'pulse_ms'=>$ms]);
                return $res2->successful();
            } catch (\Throwable $e){ Log::error('DoorService http pulse fail: '.$e->getMessage()); return false; }
        }
        // mqtt/serial not implemented yet -> log
        Log::info("DoorService mode={$mode} not implemented, treating as dummy");
        return true;
    }
}
