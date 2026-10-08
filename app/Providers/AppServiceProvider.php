<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        if (config('axelo.force_https')) {
            URL::forceScheme('https');
        }

        // Widget chatbot: 20 pesan/menit per user (atau per IP untuk guest).
        RateLimiter::for('chatbot', fn (Request $request) => Limit::perMinute(20)
            ->by($request->user()?->id ? 'user:'.$request->user()->id : 'ip:'.$request->ip())
            ->response(fn () => response()->json(['message' => 'Terlalu banyak pesan. Tunggu sebentar lalu coba lagi ya.'], 429)));
    }
}
