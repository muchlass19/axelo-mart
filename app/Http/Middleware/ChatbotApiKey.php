<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Proteksi API chatbot dengan API key statis di header X-API-KEY.
 */
class ChatbotApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('axelo.chatbot_api_key');

        if ($expected === '') {
            return response()->json(['message' => 'CHATBOT_API_KEY belum diset di .env.'], 503);
        }

        if (! hash_equals($expected, (string) $request->header('X-API-KEY'))) {
            return response()->json(['message' => 'API key tidak valid atau tidak dikirim (header X-API-KEY).'], 401);
        }

        return $next($request);
    }
}
