<?php

namespace App\Services\Chatbot;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Client minimal untuk 9router (OpenAI-compatible): POST {base_url}/chat/completions.
 */
class NineRouterClient
{
    public function isConfigured(): bool
    {
        return filled(config('services.ninerouter.base_url')) && filled(config('services.ninerouter.model'));
    }

    /**
     * Kirim percakapan ke LLM dan kembalikan `choices[0].message`.
     *
     * @throws ChatbotException
     */
    public function chat(array $messages, array $tools = [], ?string $toolChoice = null): array
    {
        if (! $this->isConfigured()) {
            throw new ChatbotException('Maaf, asisten AI belum dikonfigurasi. Admin perlu mengisi NINEROUTER_MODEL di file .env.');
        }

        $payload = ['model' => config('services.ninerouter.model'), 'messages' => $messages, 'stream' => false];
        if ($tools) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = $toolChoice ?? 'auto';
        }

        $response = $this->send($payload);

        // Sebagian model/provider tidak mendukung tool calling -> coba sekali lagi tanpa tools.
        if ($tools && in_array($response->status(), [400, 422], true)) {
            Log::warning('9router menolak request dengan tools, mencoba ulang tanpa tools.', ['body' => mb_substr($response->body(), 0, 500)]);
            unset($payload['tools'], $payload['tool_choice']);
            $payload['messages'] = array_values(array_filter($messages, fn ($m) => ($m['role'] ?? '') !== 'tool' && empty($m['tool_calls'])));
            $response = $this->send($payload);
        }

        if ($response->failed()) {
            Log::error('9router mengembalikan error.', ['status' => $response->status(), 'body' => mb_substr($response->body(), 0, 1000)]);

            throw new ChatbotException('Maaf, asisten AI sedang bermasalah (kode '.$response->status().'). Coba lagi sebentar lagi ya.');
        }

        $message = $response->json('choices.0.message');
        if (! is_array($message)) {
            Log::error('Respons 9router tidak valid.', ['body' => mb_substr($response->body(), 0, 1000)]);

            throw new ChatbotException('Maaf, asisten AI memberi respons yang tidak dikenali. Coba lagi ya.');
        }

        return $message;
    }

    private function send(array $payload): Response
    {
        $key = config('services.ninerouter.api_key');

        try {
            return Http::baseUrl(rtrim((string) config('services.ninerouter.base_url'), '/'))
                ->when(filled($key), fn ($http) => $http->withToken($key))
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(max(5, (int) config('services.ninerouter.timeout', 60)))
                ->post('/chat/completions', $payload);
        } catch (ConnectionException $e) {
            Log::warning('9router tidak bisa dihubungi.', ['error' => $e->getMessage()]);

            throw new ChatbotException('Maaf, asisten AI sedang tidak bisa dihubungi. Pastikan 9router berjalan, lalu coba lagi ya.');
        }
    }
}
