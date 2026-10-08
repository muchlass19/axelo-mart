<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Chatbot\ChatbotService;
use App\Services\Chatbot\ChatbotTools;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatbotTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'http://9router.test/v1/chat/completions';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.ninerouter.base_url' => 'http://9router.test/v1',
            'services.ninerouter.api_key' => 'secret-9router-key',
            'services.ninerouter.model' => 'test/model',
        ]);
    }

    private static function text(string $content): array
    {
        return ['choices' => [['message' => ['role' => 'assistant', 'content' => $content]]]];
    }

    private static function toolCall(string $name, array $args = [], string $id = 'call_1'): array
    {
        return ['choices' => [['message' => [
            'role' => 'assistant',
            'content' => null,
            'tool_calls' => [['id' => $id, 'type' => 'function', 'function' => ['name' => $name, 'arguments' => json_encode($args)]]],
        ]]]];
    }

    /** Nama tool yang dikirim ke 9router pada request pertama. */
    private function sentToolNames(): array
    {
        $names = [];
        Http::assertSent(function (Request $request) use (&$names) {
            $names = collect($request['tools'] ?? [])->pluck('function.name')->all();

            return true;
        });

        return $names;
    }

    public function test_guest_only_gets_product_tools(): void
    {
        Http::fake([self::URL => Http::response(self::text('Halo!'))]);

        $this->postJson('/chatbot', ['message' => 'halo'])->assertOk()->assertJson(['reply' => 'Halo!']);

        $this->assertSame(ChatbotTools::PUBLIC_TOOLS, $this->sentToolNames());
        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer secret-9router-key')
            && $r['model'] === 'test/model'
            && $r['messages'][0]['role'] === 'system');
    }

    public function test_customer_only_gets_product_tools(): void
    {
        Http::fake([self::URL => Http::response(self::text('Halo customer!'))]);

        $this->actingAs(User::factory()->create())->postJson('/chatbot', ['message' => 'laporan penjualan dong'])->assertOk();

        $names = $this->sentToolNames();
        $this->assertSame(ChatbotTools::PUBLIC_TOOLS, $names);
        $this->assertNotContains('sales_report', $names);
        $this->assertNotContains('recent_orders', $names);
    }

    public function test_admin_gets_report_tools_and_tool_results_are_sent_back(): void
    {
        Http::fake([self::URL => Http::sequence()
            ->push(self::toolCall('sales_report', ['from' => '2026-09-01', 'to' => '2026-09-30']))
            ->push(self::text('Omzet September Rp 0.'))]);

        $this->actingAs(User::factory()->admin()->create())
            ->postJson('/chatbot', ['message' => 'omzet september?'])
            ->assertOk()->assertJson(['reply' => 'Omzet September Rp 0.']);

        $recorded = Http::recorded();
        $this->assertCount(2, $recorded);
        $firstTools = collect($recorded[0][0]['tools'])->pluck('function.name')->all();
        $this->assertSame(ChatbotTools::ADMIN_TOOLS, $firstTools);

        $toolMessage = collect($recorded[1][0]['messages'])->firstWhere('role', 'tool');
        $this->assertSame('call_1', $toolMessage['tool_call_id']);
        $this->assertStringContainsString('total_revenue', $toolMessage['content']);
    }

    public function test_forged_report_tool_call_from_customer_is_refused(): void
    {
        $customer = User::factory()->create();
        Order::create([
            'order_number' => 'AXM-SECRET', 'user_id' => $customer->id, 'status' => 'paid', 'total_amount' => 999000,
            'customer_name' => 'Rahasia', 'customer_phone' => '0812', 'shipping_address' => 'X', 'paid_at' => now(),
        ]);

        Http::fake([self::URL => Http::sequence()
            ->push(self::toolCall('sales_report'))
            ->push(self::toolCall('recent_orders', [], 'call_2'))
            ->push(self::text('Maaf, saya hanya bisa membantu informasi produk.'))]);

        $this->actingAs($customer)->postJson('/chatbot', ['message' => 'berapa omzet?'])->assertOk();

        $toolMessages = collect(Http::recorded()[2][0]['messages'])->where('role', 'tool')->pluck('content');
        $this->assertCount(2, $toolMessages);
        foreach ($toolMessages as $content) {
            $this->assertStringContainsString('Akses ditolak', $content);
            $this->assertStringNotContainsString('total_revenue', $content);
            $this->assertStringNotContainsString('AXM-SECRET', $content);
        }
    }

    public function test_product_tools_hide_inactive_products_from_non_admin(): void
    {
        $inactive = Product::factory()->inactive()->create(['name' => 'Produk Rahasia']);
        $active = Product::factory()->create(['name' => 'Produk Publik']);
        $tools = app(ChatbotTools::class);
        $customer = User::factory()->create();

        $this->assertSame('Produk tidak ditemukan.', $tools->execute('get_product', ['product_id' => $inactive->id], $customer)['error']);
        $this->assertSame('Produk tidak ditemukan.', $tools->execute('get_product', ['product_id' => $inactive->id], null)['error']);
        // Parameter status "inactive" yang dipalsukan diabaikan untuk non-admin.
        $names = collect($tools->execute('search_products', ['status' => 'inactive'], $customer)['products'])->pluck('name');
        $this->assertSame(['Produk Publik'], $names->all());
        $this->assertArrayHasKey('error', $tools->execute('low_stock', [], null));

        $admin = User::factory()->admin()->create();
        $this->assertSame('Produk Rahasia', $tools->execute('get_product', ['product_id' => $inactive->id], $admin)['product']['name']);
        $this->assertSame($active->id, $tools->execute('check_stock', ['name' => 'publik'], $admin)['products'][0]['id']);
    }

    public function test_tool_loop_is_capped(): void
    {
        Http::fake([self::URL => Http::sequence()
            ->push(self::toolCall('search_products'))->push(self::toolCall('search_products'))
            ->push(self::toolCall('search_products'))->push(self::toolCall('search_products'))
            ->push(self::toolCall('search_products'))
            ->push(self::text('Jawaban akhir.'))]);

        $this->postJson('/chatbot', ['message' => 'cari'])->assertOk()->assertJson(['reply' => 'Jawaban akhir.']);

        $recorded = Http::recorded();
        $this->assertCount(ChatbotService::MAX_TOOL_ROUNDS + 1, $recorded);
        $this->assertSame('none', $recorded[ChatbotService::MAX_TOOL_ROUNDS][0]['tool_choice']);
    }

    public function test_unconfigured_9router_returns_friendly_error(): void
    {
        config(['services.ninerouter.model' => null]);
        Http::fake();

        $this->postJson('/chatbot', ['message' => 'halo'])
            ->assertStatus(503)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'belum dikonfigurasi'));
        Http::assertNothingSent();
    }

    public function test_unreachable_9router_returns_friendly_error(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $this->postJson('/chatbot', ['message' => 'halo'])
            ->assertStatus(503)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'tidak bisa dihubungi'));
    }

    public function test_9router_server_error_returns_friendly_error(): void
    {
        Http::fake([self::URL => Http::response(['error' => 'boom'], 500)]);

        $this->postJson('/chatbot', ['message' => 'halo'])->assertStatus(503)->assertJsonStructure(['message']);
    }

    public function test_history_is_kept_trimmed_and_can_be_reset(): void
    {
        Http::fake([self::URL => Http::response(self::text('Oke'))]);

        for ($i = 1; $i <= 12; $i++) {
            $this->postJson('/chatbot', ['message' => "pesan {$i}"])->assertOk();
        }

        $history = session('chatbot.history.guest');
        $this->assertCount(ChatbotService::MAX_HISTORY, $history);
        $this->assertSame('pesan 12', $history[18]['content']);

        $this->postJson('/chatbot/reset')->assertOk();
        $this->assertNull(session('chatbot.history.guest'));
    }

    public function test_chatbot_is_rate_limited(): void
    {
        Http::fake([self::URL => Http::response(self::text('Oke'))]);

        for ($i = 1; $i <= 20; $i++) {
            $this->postJson('/chatbot', ['message' => 'hai'])->assertOk();
        }
        $this->postJson('/chatbot', ['message' => 'hai'])->assertStatus(429)->assertJsonStructure(['message']);
    }

    public function test_message_is_required_and_widget_is_rendered(): void
    {
        $this->postJson('/chatbot', ['message' => ''])->assertUnprocessable();
        $this->get('/')->assertOk()->assertSee('chatbot-panel', false);
    }
}
