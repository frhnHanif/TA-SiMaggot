<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckIotApiKey
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Ambil nilai X-API-KEY dari HTTP Header request yang masuk
        $apiKeyTarget = $request->header('X-API-KEY');

        // 2. Ambil nilai API Key sah yang tersimpan di konfigurasi server
        $validKey = config('maggot.api_key');

        // 3. Jika header kosong atau tidak cocok dengan konfigurasi server, TOLAK!
        if (!$apiKeyTarget || $apiKeyTarget !== $validKey) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. API Key tidak valid atau tidak disertakan pada HTTP Header.'
            ], 401); // 401 Unauthorized
        }

        // Jika cocok, loloskan request ke Controller
        return $next($request);
    }
}