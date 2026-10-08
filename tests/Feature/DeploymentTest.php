<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DeploymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_urls_use_https_and_host_from_reverse_proxy_headers(): void
    {
        $this->get('/', ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'shop.example.com', 'X-Forwarded-Port' => '443'])
            ->assertOk()
            ->assertSee('https://shop.example.com/login', false);
    }

    public function test_seed_demo_only_seeds_an_empty_database(): void
    {
        Storage::fake('public');

        $this->artisan('app:seed-demo')->assertSuccessful();
        $this->assertSame(20, Product::count());
        $this->assertTrue(User::where('email', 'admin@axelo.test')->exists());

        $this->artisan('app:seed-demo')->expectsOutputToContain('dilewati')->assertSuccessful();
        $this->assertSame(20, Product::count());
    }
}
