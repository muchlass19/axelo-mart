<?php

return [
    // Batas stok yang dianggap "menipis" (dashboard admin & API chatbot).
    'low_stock_threshold' => (int) env('LOW_STOCK_THRESHOLD', 5),

    // API key statis untuk chatbot (header X-API-KEY) di endpoint /api/v1.
    'chatbot_api_key' => env('CHATBOT_API_KEY'),
];
