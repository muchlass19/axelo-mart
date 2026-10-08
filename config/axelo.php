<?php

return [
    // Batas stok yang dianggap "menipis" (dashboard admin & API chatbot).
    'low_stock_threshold' => (int) env('LOW_STOCK_THRESHOLD', 5),

    // Paksa semua URL yang dibuat Laravel memakai https (default: aktif saat APP_ENV=production).
    'force_https' => (bool) env('FORCE_HTTPS', env('APP_ENV') === 'production'),

    // API key statis untuk chatbot (header X-API-KEY) di endpoint /api/v1.
    'chatbot_api_key' => env('CHATBOT_API_KEY'),
];
