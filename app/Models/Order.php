<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public static function generateNumber(): string
    {
        return 'AXM-'.now()->format('ymd').'-'.strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
    }
}
