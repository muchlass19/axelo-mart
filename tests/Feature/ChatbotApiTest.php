<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatbotApiTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'test-chatbot-key';

    protected function setUp(): void
    {
        parent::setUp();
        config(['axelo.chatbot_api_key' => self::KEY]);
    }

    private function api(string $uri)
    {
        return $this->getJson($uri, ['X-API-KEY' => self::KEY]);
    }

    public function test_requests_without_or_with_wrong_api_key_are_rejected(): void
    {
        $this->getJson('/api/v1/products')->assertUnauthorized();
        $this->getJson('/api/v1/products', ['X-API-KEY' => 'salah'])->assertUnauthorized();
        $this->getJson('/api/v1/reports/sales')->assertUnauthorized();
        $this->api('/api/v1/products')->assertOk();
    }

    public function test_api_is_closed_when_key_is_not_configured(): void
    {
        config(['axelo.chatbot_api_key' => null]);
        $this->getJson('/api/v1/products', ['X-API-KEY' => ''])->assertStatus(503);
    }

    public function test_product_search_and_stock_check(): void
    {
        $kopi = Product::factory()->create(['name' => 'Kopi Arabika', 'stock' => 3]);
        Product::factory()->inactive()->create(['name' => 'Kopi Robusta']);
        Product::factory()->create(['name' => 'Teh Hijau']);

        $this->api('/api/v1/products?search=kopi&status=active')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Kopi Arabika');
        $this->api("/api/v1/products/{$kopi->id}")->assertOk()->assertJsonPath('data.stock', 3);
        $this->api('/api/v1/stock?name=kopi')->assertOk()->assertJsonPath('count', 2);
        $this->api("/api/v1/stock?id={$kopi->id}")->assertOk()->assertJsonPath('data.0.stock', 3);
        $this->api('/api/v1/stock')->assertUnprocessable();
        $this->api('/api/v1/stock/low?threshold=5')->assertOk()->assertJsonPath('data.0.name', 'Kopi Arabika');
    }

    public function test_sales_report_counts_only_paid_orders_in_period(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['name' => 'Kopi Arabika', 'price' => 50000]);

        $make = function (string $status, ?string $paidAt, int $qty) use ($user, $product) {
            $order = Order::create([
                'order_number' => Order::generateNumber(), 'user_id' => $user->id, 'status' => $status,
                'total_amount' => 50000 * $qty, 'customer_name' => 'Budi', 'customer_phone' => '0812',
                'shipping_address' => 'Jakarta', 'paid_at' => $paidAt,
            ]);
            $order->items()->create([
                'product_id' => $product->id, 'product_name' => $product->name,
                'price' => 50000, 'quantity' => $qty, 'subtotal' => 50000 * $qty,
            ]);
        };

        $make(Order::STATUS_PAID, '2026-09-10 10:00:00', 2);
        $make(Order::STATUS_COMPLETED, '2026-09-20 10:00:00', 1);
        $make(Order::STATUS_PENDING, null, 5);              // tidak dihitung
        $make(Order::STATUS_CANCELLED, null, 4);            // tidak dihitung
        $make(Order::STATUS_PAID, '2026-08-01 10:00:00', 7); // di luar periode

        $this->api('/api/v1/reports/sales?from=2026-09-01&to=2026-09-30')
            ->assertOk()
            ->assertJsonPath('total_revenue', 150000)
            ->assertJsonPath('total_orders', 2)
            ->assertJsonPath('items_sold', 3)
            ->assertJsonPath('top_products.0.product_name', 'Kopi Arabika')
            ->assertJsonPath('top_products.0.quantity_sold', 3);

        $this->api('/api/v1/reports/sales?from=2026-09-30&to=2026-09-01')->assertUnprocessable();
        $this->api('/api/v1/orders/recent?limit=3')->assertOk()->assertJsonCount(3, 'data');
    }
}
