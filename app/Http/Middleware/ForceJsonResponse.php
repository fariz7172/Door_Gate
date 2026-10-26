<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Memastikan semua request ke /api/* SELALU diperlakukan sebagai API JSON.
 * Meskipun klien (Postman/cURL/mobile) lupa mengirim header "Accept: application/json",
 * Laravel tidak akan pernah melakukan redirect 302 ke halaman HTML login saat validasi gagal.
 */
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        // Paksa header Accept menjadi application/json untuk request API
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
