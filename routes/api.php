<?php

use App\Http\Controllers\Api\V1;
use Illuminate\Support\Facades\Route;

/*
| API read-only untuk chatbot (web & WhatsApp).
| Semua endpoint wajib header: X-API-KEY: <CHATBOT_API_KEY>
*/
Route::prefix('v1')->middleware(['throttle:120,1', 'chatbot.key'])->group(function () {
    Route::get('products', [V1\ProductController::class, 'index']);
    Route::get('products/{product}', [V1\ProductController::class, 'show'])->whereNumber('product');
    Route::get('stock', [V1\StockController::class, 'check']);
    Route::get('stock/low', [V1\StockController::class, 'low']);
    Route::get('reports/sales', [V1\ReportController::class, 'sales']);
    Route::get('orders/recent', [V1\OrderController::class, 'recent']);
});
