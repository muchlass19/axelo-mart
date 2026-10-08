<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Midtrans\Config;
use Midtrans\Snap;
use Midtrans\Transaction;
use RuntimeException;

class MidtransService
{
    public function isConfigured(): bool
    {
        return filled(config('services.midtrans.server_key')) && filled(config('services.midtrans.client_key'));
    }

    public function clientKey(): ?string
    {
        return config('services.midtrans.client_key');
    }

    public function snapJsUrl(): string
    {
        return config('services.midtrans.is_production')
            ? 'https://app.midtrans.com/snap/snap.js'
            : 'https://app.sandbox.midtrans.com/snap/snap.js';
    }

    /** Buat (atau pakai ulang) Snap token untuk order. */
    public function snapToken(Order $order): string
    {
        if ($order->snap_token) {
            return $order->snap_token;
        }

        $this->boot();
        $order->loadMissing('items', 'user');

        $token = Snap::getSnapToken([
            'transaction_details' => [
                'order_id' => $order->order_number,
                'gross_amount' => $order->total_amount,
            ],
            'item_details' => $order->items->map(fn ($item) => [
                'id' => (string) ($item->product_id ?? $item->id),
                'price' => $item->price,
                'quantity' => $item->quantity,
                'name' => Str::limit($item->product_name, 45, ''),
            ])->all(),
            'customer_details' => [
                'first_name' => $order->customer_name,
                'email' => $order->user?->email,
                'phone' => $order->customer_phone,
            ],
            'callbacks' => ['finish' => route('orders.show', $order)],
            'expiry' => ['unit' => 'hours', 'duration' => 24],
        ]);

        $order->update(['snap_token' => $token]);

        return $token;
    }

    /**
     * Ambil status transaksi dari Midtrans (Transaction Status API).
     * Melempar exception dengan code 404 jika transaksi belum ada di Midtrans.
     */
    public function fetchStatus(Order $order): array
    {
        $this->boot();

        return json_decode(json_encode(Transaction::status($order->order_number)), true);
    }

    /** Verifikasi signature_key dari notifikasi Midtrans. */
    public function verifySignature(array $payload): bool
    {
        if (! isset($payload['order_id'], $payload['status_code'], $payload['gross_amount'], $payload['signature_key'])) {
            return false;
        }

        $expected = hash('sha512', $payload['order_id'].$payload['status_code'].$payload['gross_amount'].config('services.midtrans.server_key'));

        return hash_equals($expected, (string) $payload['signature_key']);
    }

    /**
     * Terapkan data transaksi Midtrans (dari webhook atau status API) ke order.
     * Order yang sudah dibayar tidak pernah diturunkan statusnya oleh Midtrans.
     */
    public function applyTransaction(Order $order, array $data): bool
    {
        $status = self::mapStatus($data['transaction_status'] ?? null, $data['fraud_status'] ?? null);

        if (! $status || $order->isPaid()) {
            return false;
        }

        if (isset($data['gross_amount']) && (int) round((float) $data['gross_amount']) !== $order->total_amount) {
            Log::warning('Midtrans gross_amount tidak cocok dengan total order.', ['order' => $order->order_number, 'data' => $data]);

            return false;
        }

        $order->transitionTo($status, array_filter([
            'payment_type' => $data['payment_type'] ?? null,
            'midtrans_transaction_id' => $data['transaction_id'] ?? null,
        ]));

        return true;
    }

    /** Mapping transaction_status Midtrans -> status order. */
    public static function mapStatus(?string $transactionStatus, ?string $fraudStatus = null): ?string
    {
        return match ($transactionStatus) {
            'capture' => $fraudStatus === 'challenge' ? Order::STATUS_PENDING : Order::STATUS_PAID,
            'settlement' => Order::STATUS_PAID,
            'pending' => Order::STATUS_PENDING,
            'deny', 'failure' => Order::STATUS_FAILED,
            'expire' => Order::STATUS_EXPIRED,
            'cancel' => Order::STATUS_CANCELLED,
            default => null, // refund dll. tidak diproses otomatis
        };
    }

    private function boot(): void
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Midtrans belum dikonfigurasi. Isi MIDTRANS_SERVER_KEY dan MIDTRANS_CLIENT_KEY di file .env.');
        }

        Config::$serverKey = config('services.midtrans.server_key');
        Config::$clientKey = config('services.midtrans.client_key');
        Config::$isProduction = (bool) config('services.midtrans.is_production');
        Config::$isSanitized = true;
        Config::$is3ds = true;
    }
}
