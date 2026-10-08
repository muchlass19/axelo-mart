<?php

namespace App\Services\Chatbot;

use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Orkestrasi percakapan: system prompt per role + loop tool calling (maks MAX_TOOL_ROUNDS).
 */
class ChatbotService
{
    public const MAX_TOOL_ROUNDS = 5;

    public const MAX_HISTORY = 20;

    public function __construct(private NineRouterClient $client, private ChatbotTools $tools) {}

    /**
     * @param  list<array{role: string, content: string}>  $history  riwayat (user/assistant saja)
     * @return array{reply: string, history: list<array{role: string, content: string}>}
     *
     * @throws ChatbotException
     */
    public function reply(?User $user, string $message, array $history = []): array
    {
        $messages = [
            ['role' => 'system', 'content' => $this->systemPrompt($user)],
            ...$history,
            ['role' => 'user', 'content' => $message],
        ];
        $definitions = $this->tools->definitions($user);
        $reply = null;

        for ($round = 0; $round < self::MAX_TOOL_ROUNDS; $round++) {
            $assistant = $this->client->chat($messages, $definitions);
            $toolCalls = $assistant['tool_calls'] ?? [];

            if (empty($toolCalls) || ! is_array($toolCalls)) {
                $reply = $this->text($assistant);
                break;
            }

            $messages[] = ['role' => 'assistant', 'content' => $assistant['content'] ?? null, 'tool_calls' => $toolCalls];
            foreach ($toolCalls as $call) {
                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => (string) ($call['id'] ?? ''),
                    'content' => json_encode($this->runToolCall($call, $user), JSON_UNESCAPED_UNICODE),
                ];
            }
        }

        // Batas loop tercapai: minta jawaban akhir tanpa memanggil tool lagi.
        if ($reply === null) {
            $reply = $this->text($this->client->chat($messages, $definitions, 'none'));
        }

        $reply = $reply !== '' ? $reply : 'Maaf, saya belum bisa menjawab pertanyaan itu. Coba tanyakan dengan kalimat lain ya.';
        $history = array_slice([...$history, ['role' => 'user', 'content' => $message], ['role' => 'assistant', 'content' => $reply]], -self::MAX_HISTORY);

        return ['reply' => $reply, 'history' => $history];
    }

    private function runToolCall(array $call, ?User $user): array
    {
        $name = (string) ($call['function']['name'] ?? '');
        $args = json_decode((string) ($call['function']['arguments'] ?? '{}'), true);
        $result = $this->tools->execute($name, is_array($args) ? $args : [], $user);

        Log::info('Chatbot tool call', ['tool' => $name, 'user_id' => $user?->id, 'role' => $user?->role ?? 'guest', 'denied' => isset($result['error'])]);

        return $result;
    }

    private function text(array $assistant): string
    {
        $content = $assistant['content'] ?? '';
        if (is_array($content)) { // sebagian provider mengirim array of parts
            $content = collect($content)->map(fn ($part) => is_array($part) ? ($part['text'] ?? '') : (string) $part)->implode('');
        }

        return trim((string) $content);
    }

    private function systemPrompt(?User $user): string
    {
        $today = now()->locale('id')->translatedFormat('l, d F Y');
        $store = config('app.name');

        if (ChatbotTools::isAdmin($user)) {
            $scope = <<<TXT
Kamu sedang membantu ADMIN toko ({$user->name}). Kamu boleh menjawab tentang semua produk (aktif maupun nonaktif), stok dan stok menipis, laporan penjualan per periode, serta pesanan terbaru dan jumlah per status.
Untuk laporan penjualan, hitung tanggal sendiri dari tanggal hari ini (misal "bulan ini", "7 hari terakhir") lalu panggil tool sales_report dengan format YYYY-MM-DD.
TXT;
        } else {
            $who = $user ? "customer bernama {$user->name}" : 'pengunjung (belum login)';
            $scope = <<<TXT
Kamu sedang membantu {$who}. Kamu HANYA boleh memberi informasi produk: mencari/menampilkan produk yang dijual, detail produk, harga, dan ketersediaan stok.
Kamu TIDAK punya akses ke laporan penjualan, omzet, data pesanan (termasuk pesanan milik pengguna sendiri maupun orang lain), data pelanggan, produk nonaktif, atau data admin. Jika diminta, tolak dengan sopan dan jelaskan bahwa kamu hanya bisa membantu informasi produk. Untuk status pesanan, arahkan pengguna ke menu "Pesanan Saya".
TXT;
        }

        return <<<TXT
Kamu adalah asisten AI toko online "{$store}". Jawab dalam Bahasa Indonesia yang ramah, singkat, dan jelas. Hari ini {$today}.
{$scope}
Aturan:
- Selalu gunakan tool yang tersedia untuk mengambil data; jangan mengarang produk, harga, stok, atau angka.
- Jika data tidak ditemukan, katakan terus terang.
- Tulis harga dalam format Rupiah, contoh: Rp 85.000.
- Tolak dengan sopan permintaan di luar topik toko ini.
- Gunakan teks biasa (boleh daftar dengan tanda "-"), tanpa tabel atau format markdown yang rumit.
TXT;
    }
}
