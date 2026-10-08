<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

#[Fillable([
    'order_number', 'user_id', 'status', 'total_amount', 'customer_name', 'customer_phone',
    'shipping_address', 'notes', 'payment_type', 'snap_token', 'midtrans_transaction_id',
    'stock_deducted', 'paid_at',
])]
class Order extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_SHIPPED = 'shipped';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING => 'Menunggu Pembayaran',
        self::STATUS_PAID => 'Dibayar',
        self::STATUS_SHIPPED => 'Dikirim',
        self::STATUS_COMPLETED => 'Selesai',
        self::STATUS_FAILED => 'Gagal',
        self::STATUS_EXPIRED => 'Kedaluwarsa',
        self::STATUS_CANCELLED => 'Dibatalkan',
    ];

    /** Status yang dihitung sebagai penjualan (sudah dibayar). */
    public const SALES_STATUSES = [self::STATUS_PAID, self::STATUS_SHIPPED, self::STATUS_COMPLETED];

    protected function casts(): array
    {
        return [
            'total_amount' => 'integer',
            'stock_deducted' => 'boolean',
            'paid_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function scopeSales(Builder $query): Builder
    {
        return $query->whereIn('status', self::SALES_STATUSES);
    }

    public function isPaid(): bool
    {
        return in_array($this->status, self::SALES_STATUSES, true);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Ubah status order sekaligus mengatur stok:
     * - masuk status "sudah dibayar" (paid/shipped/completed) -> stok dikurangi (sekali saja)
     * - keluar dari status dibayar (cancelled/failed/expired/pending) -> stok dikembalikan
     * Flag stock_deducted membuat proses ini idempotent (aman dipanggil webhook + cek status).
     */
    public function transitionTo(string $status, array $attributes = []): void
    {
        DB::transaction(function () use ($status, $attributes) {
            /** @var self $order */
            $order = static::whereKey($this->getKey())->lockForUpdate()->firstOrFail();
            $becomesPaid = in_array($status, self::SALES_STATUSES, true);

            if ($becomesPaid && ! $order->stock_deducted) {
                $order->adjustStock(-1);
                $order->stock_deducted = true;
            } elseif (! $becomesPaid && $order->stock_deducted) {
                $order->adjustStock(1);
                $order->stock_deducted = false;
            }

            if ($becomesPaid && ! $order->paid_at) {
                $order->paid_at = now();
            }

            $order->fill($attributes);
            $order->status = $status;
            $order->save();

            $this->setRawAttributes($order->getAttributes(), true);
        });
    }

    /** $direction -1 = kurangi stok, 1 = kembalikan stok. Dipanggil di dalam transaksi. */
    private function adjustStock(int $direction): void
    {
        foreach ($this->items()->whereNotNull('product_id')->get() as $item) {
            $product = Product::whereKey($item->product_id)->lockForUpdate()->first();
            if (! $product) {
                continue;
            }

            if ($direction > 0) {
                $product->increment('stock', $item->quantity);

                continue;
            }

            if ($product->stock < $item->quantity) {
                Log::warning('Stok tidak cukup saat order dibayar (oversold).', [
                    'order' => $this->order_number, 'product_id' => $product->id,
                    'stock' => $product->stock, 'qty' => $item->quantity,
                ]);
            }
            $product->decrement('stock', min($product->stock, $item->quantity));
        }
    }

    public static function generateNumber(): string
    {
        return 'AXM-'.now()->format('ymd').'-'.strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
    }
}
