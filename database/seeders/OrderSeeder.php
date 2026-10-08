<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Order dummy tersebar di ~60 hari terakhir supaya laporan punya data.
 * Stok produk TIDAK diubah di sini (stok di ProductSeeder dianggap stok saat ini).
 */
class OrderSeeder extends Seeder
{
    private const ADDRESSES = [
        'Jl. Merdeka No. 10, Bandung', 'Jl. Sudirman No. 45, Jakarta Pusat', 'Jl. Malioboro No. 7, Yogyakarta',
        'Jl. Pahlawan No. 21, Surabaya', 'Jl. Gajah Mada No. 3, Semarang',
    ];

    private const PAYMENT_TYPES = ['bank_transfer', 'gopay', 'qris', 'credit_card', 'shopeepay'];

    public function run(): void
    {
        $customers = User::where('role', User::ROLE_CUSTOMER)->get();
        $products = Product::where('status', Product::STATUS_ACTIVE)->get();

        for ($i = 1; $i <= 90; $i++) {
            $daysAgo = $i > 82 ? mt_rand(0, 2) : mt_rand(0, 60); // beberapa order terbaru (ada yang pending)
            $createdAt = Carbon::now()->subDays($daysAgo)->setTime(mt_rand(7, 22), mt_rand(0, 59));
            if ($createdAt->isFuture()) {
                $createdAt = Carbon::now()->subMinutes(mt_rand(5, 120));
            }
            $status = $this->randomStatus($daysAgo);
            $isPaid = in_array($status, Order::SALES_STATUSES, true);
            $customer = $customers->random();

            $items = $products->random(mt_rand(1, 3))->map(function (Product $product) {
                $qty = mt_rand(1, 3);

                return [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'price' => $product->price,
                    'quantity' => $qty,
                    'subtotal' => $product->price * $qty,
                ];
            });

            $order = Order::forceCreate([
                'order_number' => sprintf('AXM-%s-S%03d', $createdAt->format('ymd'), $i),
                'user_id' => $customer->id,
                'status' => $status,
                'total_amount' => $items->sum('subtotal'),
                'customer_name' => $customer->name,
                'customer_phone' => $customer->phone,
                'shipping_address' => self::ADDRESSES[array_rand(self::ADDRESSES)],
                'payment_type' => $isPaid ? self::PAYMENT_TYPES[array_rand(self::PAYMENT_TYPES)] : null,
                'stock_deducted' => $isPaid,
                'paid_at' => $isPaid ? $createdAt->copy()->addMinutes(mt_rand(2, 90)) : null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            $order->items()->createMany($items->all());
        }
    }

    private function randomStatus(int $daysAgo): string
    {
        $roll = mt_rand(1, 100);

        if ($daysAgo <= 2) {
            return match (true) {
                $roll <= 35 => Order::STATUS_PENDING,
                $roll <= 75 => Order::STATUS_PAID,
                default => Order::STATUS_SHIPPED,
            };
        }

        return match (true) {
            $roll <= 70 => Order::STATUS_COMPLETED,
            $roll <= 78 => Order::STATUS_SHIPPED,
            $roll <= 86 => Order::STATUS_CANCELLED,
            $roll <= 94 => Order::STATUS_EXPIRED,
            default => Order::STATUS_FAILED,
        };
    }
}
