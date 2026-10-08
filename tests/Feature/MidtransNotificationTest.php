<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MidtransNotificationTest extends TestCase
{
    use RefreshDatabase;

    private const SERVER_KEY = 'SB-Mid-server-test';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.midtrans.server_key' => self::SERVER_KEY, 'services.midtrans.client_key' => 'SB-Mid-client-test']);
    }

    private function makeOrder(Product $product, int $qty): Order
    {
        $order = Order::create([
            'order_number' => 'AXM-TEST-001', 'user_id' => User::factory()->create()->id,
            'total_amount' => $product->price * $qty, 'customer_name' => 'Budi',
            'customer_phone' => '0812', 'shipping_address' => 'Jakarta',
        ]);
        $order->items()->create([
            'product_id' => $product->id, 'product_name' => $product->name,
            'price' => $product->price, 'quantity' => $qty, 'subtotal' => $product->price * $qty,
        ]);

        return $order;
    }

    private function payload(Order $order, string $status, ?string $signature = null): array
    {
        $gross = $order->total_amount.'.00';

        return [
            'order_id' => $order->order_number,
            'status_code' => '200',
            'gross_amount' => $gross,
            'transaction_status' => $status,
            'fraud_status' => 'accept',
            'payment_type' => 'bank_transfer',
            'transaction_id' => 'trx-123',
            'signature_key' => $signature ?? hash('sha512', $order->order_number.'200'.$gross.self::SERVER_KEY),
        ];
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $order = $this->makeOrder(Product::factory()->create(), 1);

        $this->postJson('/midtrans/notification', $this->payload($order, 'settlement', 'invalid'))->assertForbidden();
        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
    }

    public function test_settlement_marks_order_paid_and_deducts_stock_once(): void
    {
        $product = Product::factory()->create(['stock' => 10]);
        $order = $this->makeOrder($product, 3);

        // Dikirim dua kali (Midtrans bisa retry) -> stok tetap hanya dipotong sekali.
        $this->postJson('/midtrans/notification', $this->payload($order, 'settlement'))->assertOk();
        $this->postJson('/midtrans/notification', $this->payload($order, 'settlement'))->assertOk();

        $order->refresh();
        $this->assertSame(Order::STATUS_PAID, $order->status);
        $this->assertSame('bank_transfer', $order->payment_type);
        $this->assertNotNull($order->paid_at);
        $this->assertSame(7, $product->fresh()->stock);
    }

    public function test_expire_marks_order_expired_without_touching_stock(): void
    {
        $product = Product::factory()->create(['stock' => 10]);
        $order = $this->makeOrder($product, 3);

        $this->postJson('/midtrans/notification', $this->payload($order, 'expire'))->assertOk();

        $this->assertSame(Order::STATUS_EXPIRED, $order->fresh()->status);
        $this->assertSame(10, $product->fresh()->stock);
    }
}
