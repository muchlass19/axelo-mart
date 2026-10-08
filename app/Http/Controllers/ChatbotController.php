<?php

namespace App\Http\Controllers;

use App\Services\Chatbot\ChatbotException;
use App\Services\Chatbot\ChatbotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class ChatbotController extends Controller
{
    /** POST /chatbot  {message} */
    public function send(Request $request, ChatbotService $chatbot): JsonResponse
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:1000']], [
            'message.required' => 'Pesan tidak boleh kosong.',
            'message.max' => 'Pesan terlalu panjang (maks 1000 karakter).',
        ]);

        $key = self::historyKey($request);

        try {
            $result = $chatbot->reply($request->user(), trim($data['message']), session($key, []));
        } catch (ChatbotException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        } catch (Throwable $e) {
            Log::error('Chatbot error', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Maaf, terjadi kesalahan pada asisten AI. Coba lagi nanti ya.'], 500);
        }

        session([$key => $result['history']]);

        return response()->json(['reply' => $result['reply']]);
    }

    /** POST /chatbot/reset */
    public function reset(Request $request): JsonResponse
    {
        session()->forget(self::historyKey($request));

        return response()->json(['message' => 'Percakapan direset.']);
    }

    /** Riwayat dipisah per user (guest punya key sendiri) supaya tidak terbawa saat ganti akun. */
    public static function historyKey(Request $request): string
    {
        return 'chatbot.history.'.($request->user()?->id ?? 'guest');
    }
}
