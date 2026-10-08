{{-- Widget chatbot AI: tombol melayang + panel chat (vanilla JS). --}}
@php($chatHistory = session(\App\Http\Controllers\ChatbotController::historyKey(request()), []))
<style>
    #chatbot-toggle { position: fixed; right: 20px; bottom: 20px; width: 56px; height: 56px; z-index: 1050; }
    #chatbot-panel { position: fixed; right: 20px; bottom: 88px; width: 360px; max-width: calc(100vw - 40px); height: 480px; max-height: calc(100vh - 120px); z-index: 1050; }
    #chatbot-messages { overflow-y: auto; background: #f8f9fa; }
    .chat-msg { max-width: 85%; white-space: pre-wrap; word-wrap: break-word; padding: .5rem .75rem; border-radius: .75rem; margin-bottom: .5rem; font-size: .9rem; }
    .chat-user { background: #0d6efd; color: #fff; margin-left: auto; }
    .chat-assistant { background: #fff; border: 1px solid #dee2e6; }
    .chat-error { background: #fff3cd; border: 1px solid #ffe69c; }
</style>

<button id="chatbot-toggle" class="btn btn-primary rounded-circle shadow fs-4" type="button" title="Tanya asisten AI">💬</button>

<div id="chatbot-panel" class="card shadow d-none" data-send-url="{{ route('chatbot.send') }}" data-reset-url="{{ route('chatbot.reset') }}">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center py-2">
        <span class="fw-semibold">🤖 Asisten {{ config('app.name') }}</span>
        <span>
            <button type="button" id="chatbot-reset" class="btn btn-sm btn-light py-0" title="Mulai percakapan baru">Reset</button>
            <button type="button" id="chatbot-close" class="btn-close btn-close-white btn-sm ms-1 align-middle"></button>
        </span>
    </div>
    <div id="chatbot-messages" class="card-body d-flex flex-column">
        <div class="chat-msg chat-assistant">Halo! 👋 {{ auth()->user()?->isAdmin() ? 'Saya bisa bantu info produk, stok, laporan penjualan, dan pesanan terbaru.' : 'Saya bisa bantu cari produk, harga, dan cek stok.' }}</div>
        @foreach ($chatHistory as $msg)
            <div class="chat-msg chat-{{ $msg['role'] }}">{{ $msg['content'] }}</div>
        @endforeach
    </div>
    <form id="chatbot-form" class="card-footer d-flex gap-2 p-2">
        <input type="text" id="chatbot-input" class="form-control form-control-sm" placeholder="Tulis pertanyaan..." maxlength="1000" autocomplete="off" required>
        <button class="btn btn-sm btn-primary" id="chatbot-send">Kirim</button>
    </form>
</div>

<script>
(() => {
    const panel = document.getElementById('chatbot-panel');
    const box = document.getElementById('chatbot-messages');
    const form = document.getElementById('chatbot-form');
    const input = document.getElementById('chatbot-input');
    const sendBtn = document.getElementById('chatbot-send');
    const csrf = document.querySelector('meta[name="csrf-token"]').content;

    const add = (text, type) => {
        const el = document.createElement('div');
        el.className = 'chat-msg chat-' + type;
        el.textContent = text; // textContent: aman dari XSS
        box.appendChild(el);
        box.scrollTop = box.scrollHeight;
        return el;
    };

    const post = async (url, body) => {
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify(body || {}),
        });
        let data = {};
        try { data = await res.json(); } catch (e) {}
        return { ok: res.ok, status: res.status, data };
    };

    document.getElementById('chatbot-toggle').addEventListener('click', () => {
        panel.classList.toggle('d-none');
        box.scrollTop = box.scrollHeight;
        input.focus();
    });
    document.getElementById('chatbot-close').addEventListener('click', () => panel.classList.add('d-none'));

    document.getElementById('chatbot-reset').addEventListener('click', async () => {
        await post(panel.dataset.resetUrl);
        box.querySelectorAll('.chat-msg:not(:first-child)').forEach(el => el.remove());
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const message = input.value.trim();
        if (!message) return;
        add(message, 'user');
        input.value = '';
        sendBtn.disabled = true;
        const typing = add('Mengetik...', 'assistant');
        try {
            const { ok, status, data } = await post(panel.dataset.sendUrl, { message });
            typing.remove();
            if (ok) add(data.reply, 'assistant');
            else add(status === 419 ? 'Sesi kedaluwarsa, silakan muat ulang halaman.' : (data.message || 'Maaf, terjadi kesalahan.'), 'error');
        } catch (err) {
            typing.remove();
            add('Maaf, tidak bisa terhubung ke server.', 'error');
        } finally {
            sendBtn.disabled = false;
            input.focus();
        }
    });
})();
</script>
