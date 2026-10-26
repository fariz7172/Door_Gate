<?php
namespace App\Http\Middleware;

use App\Models\PersonalAccessToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware proteksi API — mendukung DUA mode:
 *
 *   1. Bearer Token  : Authorization: Bearer <PLAIN_TOKEN>
 *   2. API Key (X-API-Key) : X-API-Key: <PLAIN_TOKEN>
 *
 * Token disimpan di DB dalam bentuk hash sha256 (plain text hanya muncul
 * sekali saat di-generate). Middleware memvalidasi token + expiry + ability.
 *
 * Token bisa di-generate lewat artisan:
 *   php artisan token:create <user_id> <name> [--abilities=*] [--days=365]
 */
class EnsureValidApiToken
{
    public function handle(Request $request, Closure $next, ...$args): Response
    {
        $plainToken = $this->extractToken($request);

        if (empty($plainToken)) {
            return $this->deny($request, 'Token tidak ditemukan. Kirim header Authorization: Bearer [REDACTED] atau X-API-Key.', 401);
        }

        $token = PersonalAccessToken::findValidToken($plainToken);

        if (!$token) {
            return $this->deny($request, 'Token tidak valid atau sudah kedaluwarsa.', 401);
        }

        // Cek ability kalau middleware ditulis: ->middleware('api.token:usersprofile:read')
        $requiredAbility = $args[0] ?? null;
        if ($requiredAbility && !$token->can($requiredAbility)) {
            return $this->deny($request, "Token tidak memiliki izin '{$requiredAbility}'.", 403);
        }

        // Inject user + token ke request supaya controller bisa akses
        $request->attributes->set('api_token', $token);
        if ($token->tokenable) {
            $request->attributes->set('api_user', $token->tokenable);
            auth()->setUser($token->tokenable);
        }

        return $next($request);
    }

    protected function extractToken(Request $request): ?string
    {
        // 1. Authorization: Bearer [REDACTED]
        //    CATATAN: php artisan serve (built-in) di Windows memotong header
        //    Authorization jika ada spasi -> selalu kirim via X-API-Key atau ?token=[REDACTED]
        $header = $request->header('Authorization');
        if ($header) {
            if (preg_match('/Bearer\s+(.+)/i', $header, $m)) return trim($m[1]);
            // header tanpa keyword Bearer (raw token)
            $cleaned = trim(preg_replace('/^(Bearer|Basic)\s*/i', '', $header));
            if ($cleaned !== '') return $cleaned;
        }
        // 2. X-API-Key (paling andalan di built-in server)
        $apiKey = $request->header('X-API-Key');
        if ($apiKey) return trim($apiKey);
        // 3. Query string ?token=[REDACTED]
        $queryToken = $request->input('token') ?: $request->input('api_token');
        if ($queryToken) return trim($queryToken);
        return null;
    }

    protected function deny(Request $request, string $message, int $status): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
            ], $status);
        }
        return response($message, $status);
    }
}
