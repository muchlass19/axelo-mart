<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_product_with_multiple_images(): void
    {
        Storage::fake('public');

        $this->actingAs(User::factory()->admin()->create())
            ->post('/admin/products', [
                'name' => 'Kopi Gayo', 'price' => 75000, 'stock' => 10, 'status' => 'active',
                'images' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.png')],
            ])->assertRedirect(route('admin.products.index'));

        $product = Product::where('name', 'Kopi Gayo')->firstOrFail();
        $this->assertCount(2, $product->images);
        Storage::disk('public')->assertExists($product->images->first()->path);
    }

    public function test_marking_order_paid_deducts_stock_once_and_cancel_restores_it(): void
    {
        $product = Product::factory()->create(['stock' => 10]);
        $order = Order::create([
            'order_number' => 'AXM-TEST-1', 'user_id' => User::factory()->create()->id, 'total_amount' => 3 * $product->price,
            'customer_name' => 'Budi', 'customer_phone' => '0812', 'shipping_address' => 'Jakarta',
        ]);
        $order->items()->create([
            'product_id' => $product->id, 'product_name' => $product->name,
            'price' => $product->price, 'quantity' => 3, 'subtotal' => 3 * $product->price,
        ]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->patch("/admin/orders/{$order->id}/status", ['status' => 'paid']);
        $this->actingAs($admin)->patch("/admin/orders/{$order->id}/status", ['status' => 'completed']);
        $this->assertSame(7, $product->fresh()->stock);
        $this->assertNotNull($order->fresh()->paid_at);

        $this->actingAs($admin)->patch("/admin/orders/{$order->id}/status", ['status' => 'cancelled']);
        $this->assertSame(10, $product->fresh()->stock);
    }
}
