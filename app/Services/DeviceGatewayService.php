<?php
namespace App\Services;
use App\Models\ApiEndpoint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeviceGatewayService {
    // Call any endpoint from DB by id with optional payload override
    public function call(int $endpointId, array $payload = [], ?string $overrideUrl = null): array {
        $ep = ApiEndpoint::findOrFail($endpointId);
        $url = $overrideUrl ?? $ep->url;
        $method = strtoupper($ep->http_method ?? 'POST');
        $timeout = 8;

        $headers = ['Accept' => 'application/json'];
        $body = array_merge(
            $ep->default_payload ? (json_decode($ep->default_payload, true) ?? []) : [],
            $payload
        );

        $start = microtime(true);
        try {
            $req = Http::withHeaders($headers)->timeout($timeout)->acceptJson();
            // For local devices, don't verify SSL and allow HTTP
            if (str_starts_with($url, 'http://')) {
                $req = $req->withoutVerifying();
            }

            $res = match($method) {
                'GET'    => $req->get($url, $body),
                'DELETE' => $req->delete($url, $body),
                'PUT'    => $req->put($url, $body),
                'PATCH'  => $req->patch($url, $body),
                default  => $req->post($url, $body),
            };

            $elapsed = round((microtime(true) - $start) * 1000);
            return [
                'ok' => $res->successful(),
                'status' => $res->status(),
                'elapsed_ms' => $elapsed,
                'body' => $this->safeJson($res->body()),
                'headers' => $res->headers(),
                'endpoint' => ['id'=>$ep->id, 'url'=>$ep->url, 'desc'=>$ep->desc],
            ];
        } catch (\Throwable $e) {
            $elapsed = round((microtime(true) - $start) * 1000);
            Log::warning('DeviceGatewayService call failed ['.$ep->id.'] '.$url.' : '.$e->getMessage());
            return [
                'ok' => false,
                'status' => 0,
                'elapsed_ms' => $elapsed,
                'body' => ['error' => $e->getMessage()],
                'endpoint' => ['id'=>$ep->id, 'url'=>$ep->url, 'desc'=>$ep->desc],
            ];
        }
    }

    // Convenience: open gate via known opengate endpoint(s)
    public function openGate(?int $endpointId = null, array $extra = []): array {
        if ($endpointId) {
            return $this->call($endpointId, $extra);
        }
        // Auto-find first opengate endpoint
        $ep = ApiEndpoint::where('url', 'like', '%opengate%')->active()->first()
           ?? ApiEndpoint::where('desc', 'like', '%opengate%')->first();
        if (!$ep) return ['ok'=>false,'status'=>404,'body'=>['error'=>'Opengate endpoint not configured']];
        return $this->call($ep->id, $extra);
    }

    protected function safeJson(string $raw): mixed {
        $decoded = json_decode($raw, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $raw;
    }
}
