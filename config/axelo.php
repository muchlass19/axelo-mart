<?php

return [
    // Batas stok yang dianggap "menipis" (dashboard admin & API chatbot).
    'low_stock_threshold' => (int) env('LOW_STOCK_THRESHOLD', 5),
];
