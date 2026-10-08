<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_shows_only_active_products_in_stock(): void
    {
        Product::factory()->create(['name' => 'Produk Tampil']);
        Product::factory()->inactive()->create(['name' => 'Produk Nonaktif']);
        Product::factory()->create(['name' => 'Produk Habis', 'stock' => 0]);

        $this->get('/')->assertOk()
            ->assertSee('Produk Tampil')
            ->assertDontSee('Produk Nonaktif')
            ->assertDontSee('Produk Habis');
    }

    public function test_checkout_without_midtrans_keys_creates_pending_order_without_crashing(): void
    {
        config(['services.midtrans.server_key' => null, 'services.midtrans.client_key' => null]);
        $customer = User::factory()->create();
        $product = Product::factory()->create(['price' => 10000, 'stock' => 5]);

        $this->actingAs($customer)->post("/cart/{$product->id}", ['quantity' => 2]);
        $response = $this->actingAs($customer)->post('/checkout', [
            'customer_name' => 'Budi', 'customer_phone' => '0812', 'shipping_address' => 'Jl. Merdeka 1',
        ]);

        $order = Order::firstOrFail();
        $response->assertRedirect(route('orders.show', $order));
        $this->assertSame(Order::STATUS_PENDING, $order->status);
        $this->assertSame(20000, $order->total_amount);
        $this->assertSame(5, $product->fresh()->stock, 'Stok belum dipotong sebelum pembayaran sukses');

        $this->actingAs($customer)->get(route('orders.show', $order))
            ->assertOk()->assertSee('belum dikonfigurasi');
    }

    public function test_admin_cannot_use_cart(): void
    {
        $product = Product::factory()->create();
        $this->actingAs(User::factory()->admin()->create())->post("/cart/{$product->id}")->assertForbidden();
    }
}
