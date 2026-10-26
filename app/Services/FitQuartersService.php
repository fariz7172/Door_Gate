<?php
namespace App\Services;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FitQuartersService {
    protected string $baseUrl;
    protected string $apiKey;
    protected int $timeout;

    public function __construct(){
        $this->baseUrl = rtrim(config('services.fitquarters.base_url','https://api.fitquarters.id'), '/');
        $this->apiKey  = (string) config('services.fitquarters.key');
        $this->timeout = (int) config('services.fitquarters.timeout', 5);
        // allow override from DB gym_settings if exists
        try {
            if(\Schema::hasTable('gym_settings')){
                $s = \App\Models\GymSetting::first();
                if($s){
                    if($s->api_base_url) $this->baseUrl = rtrim($s->api_base_url,'/');
                    if($s->api_key) $this->apiKey = $s->api_key;
                }
            }
        } catch (\Throwable $e) {}
    }

    protected function headers(): array {
        return [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
        ];
    }

    public function healthCheck(): bool {
        if(empty($this->apiKey)) return false;
        try {
            $res = Http::withHeaders($this->headers())->timeout($this->timeout)->get($this->baseUrl . '/v1/check_ins', ['limit'=>1]);
            return $res->successful();
        } catch (\Throwable $e) { Log::warning('FitQuarters healthCheck fail: '.$e->getMessage()); return false; }
    }

    public function getAccessEvents(int $limit=50): array {
        $res = Http::withHeaders($this->headers())->timeout($this->timeout)->get($this->baseUrl . '/v1/access_events', ['limit'=>$limit]);
        if(!$res->successful()) throw new \RuntimeException('FitQuarters getAccessEvents failed: '.$res->status().' '.$res->body());
        return $res->json();
    }

    public function checkIn(string $memberId, string $deviceId, ?string $checkedInAt=null): array {
        $payload = ['member_id'=>$memberId,'device_id'=>$deviceId,'checked_in_at'=>$checkedInAt ?? now()->toIso8601String()];
        $res = Http::withHeaders($this->headers())->timeout($this->timeout)->post($this->baseUrl . '/v1/check_ins', $payload);
        if($res->status()===201 || $res->status()===200) return $res->json();
        throw new \RuntimeException($res->body(), $res->status());
    }

    public function checkOut(string $memberId, string $deviceId, ?string $checkedOutAt=null): array {
        $payload = ['member_id'=>$memberId,'device_id'=>$deviceId,'checked_out_at'=>$checkedOutAt ?? now()->toIso8601String()];
        $res = Http::withHeaders($this->headers())->timeout($this->timeout)->post($this->baseUrl . '/v1/check_outs', $payload);
        if($res->successful()) return $res->json();
        throw new \RuntimeException($res->body(), $res->status());
    }

    public function getMembers(int $limit=20): array {
        $res = Http::withHeaders($this->headers())->timeout($this->timeout)->get($this->baseUrl . '/v1/members', ['limit'=>$limit]);
        if(!$res->successful()) throw new \RuntimeException('getMembers failed: '.$res->body());
        return $res->json();
    }

    public function getMemberAccess(string $memberId): array {
        $res = Http::withHeaders($this->headers())->timeout($this->timeout)->get($this->baseUrl . '/v1/members/' . urlencode($memberId) . '/access');
        if(!$res->successful()) throw new \RuntimeException($res->body(), $res->status());
        return $res->json();
    }
}
