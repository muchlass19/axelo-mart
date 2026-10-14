<?php

namespace App\Services\Chatbot;

use App\Http\Controllers\CartController;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CatalogService;
use App\Services\ReportService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Definisi & eksekusi tools (function calling) untuk chatbot.
 *
 * Akses dibatasi di server dua lapis:
 *  1. Hanya definisi tool yang diizinkan untuk role tersebut yang dikirim ke LLM.
 *  2. Saat tool dieksekusi, role dicek ulang (tool di luar izin ditolak) dan
 *     tool produk untuk guest/customer selalu dibatasi ke produk aktif.
 */
class ChatbotTools
{
    public const PUBLIC_TOOLS = ['search_products', 'get_product', 'check_stock', 'add_product_to_cart', 'remove_product_from_cart', 'update_cart_quantity'];

    public const CUSTOMER_TOOLS = [...self::PUBLIC_TOOLS, 'checkout_order'];

    public const ADMIN_TOOLS = [...self::PUBLIC_TOOLS, 'low_stock', 'sales_report', 'recent_orders'];

    public function __construct(private CatalogService $catalog, private ReportService $reports) {}

    public static function isAdmin(?User $user): bool
    {
        return (bool) $user?->isAdmin();
    }

    /** @return list<string> */
    public function allowedNames(?User $user): array
    {
        if(!$user) {
            return self::PUBLIC_TOOLS;
        }
        
        return self::isAdmin($user) ? self::ADMIN_TOOLS : self::CUSTOMER_TOOLS;
    }

    /** Definisi tools format OpenAI untuk role user. */
    public function definitions(?User $user): array
    {
        $admin = self::isAdmin($user);

        $searchProps = [
            'query' => ['type' => 'string', 'description' => 'Kata kunci nama produk. Kosongkan untuk melihat semua.'],
            'limit' => ['type' => 'integer', 'description' => 'Jumlah maksimal hasil (1-20, default 10).'],
        ];
        if ($admin) {
            $searchProps['status'] = ['type' => 'string', 'enum' => ['active', 'inactive', 'all'], 'description' => 'Filter status produk (default all).'];
        }

        $tools = [
            $this->tool('search_products', $admin
                ? 'Cari/daftar produk (semua status) beserta harga dan stok.'
                : 'Cari/daftar produk yang dijual di toko beserta harga dan ketersediaan stok.', $searchProps),
            $this->tool('get_product', 'Detail satu produk berdasarkan ID (nama, harga, stok, deskripsi, link).', [
                'product_id' => ['type' => 'integer', 'description' => 'ID produk.'],
            ], ['product_id']),
            $this->tool('check_stock', 'Cek stok produk berdasarkan ID atau nama produk.', [
                'product_id' => ['type' => 'integer', 'description' => 'ID produk (opsional jika name diisi).'],
                'name' => ['type' => 'string', 'description' => 'Nama/kata kunci produk (opsional jika product_id diisi).'],
            ]),
            $this->tool('add_product_to_cart', 'Tambahkan produk aktif ke keranjang belanja. Gunakan product_id dari hasil pencarian atau detail produk. Quantity adalah jumlah tambahan, bukan total jumlah di keranjang.', [
                'product_id' => ['type' => 'integer', 'description' => 'ID Produk yang ingin ditambahkan'],
                'quantity' => ['type' => 'integer', 'description' => 'Jumlah produk yang ingin ditambahkan, minimal 1']
            ], ['product_id', 'quantity']),
            $this->tool(
                'remove_product_from_cart',
                'Menghapus produk dari keranjang belanja pelanggan yang sedang login.',
                [
                    'product_id' => [
                        'type' => 'integer',
                        'description' => 'ID produk yang ingin dihapus dari keranjang.',
                    ],
                ],
                ['product_id'],
            ),
            $this->tool(
                'update_cart_quantity',
                'WAJIB gunakan tool ini saat pelanggan meminta MENGUBAH, MENGGANTI, MENAIKKAN, atau MENURUNKAN jumlah produk yang SUDAH ADA di keranjang. Parameter product_id harus menggunakan ID produk yang sesuai dengan item keranjang. Parameter quantity adalah JUMLAH AKHIR yang diinginkan, bukan selisih atau jumlah tambahan. Jangan mengklaim berhasil sebelum tool mengembalikan success=true.',
                [
                    'product_id' => [
                        'type' => 'integer',
                        'description' => 'ID produk yang sudah ada di keranjang.',
                    ],
                    'quantity' => [
                        'type' => 'integer',
                        'description' => 'Jumlah akhir produk yang diinginkan, minimal 1.',
                    ],
                ],
                ['product_id', 'quantity'],
            ),
        ];

        if ($user && !$admin) {
            $tools[] = $this->tool(
                'checkout_order',
                'Buat pesanan dari keranjang belanja customer yang sedang login. Gunakan hanya setelah customer mengonfirmasi checkout dan memberikan nama penerima, nomor telepon, serta alamat pengiriman. Harga dan total dihitung oleh server.',
                [
                    'customer_name' => [
                        'type' => 'string',
                        'description' => 'Nama penerima pesanan.',
                    ],
                    'customer_phone' => [
                        'type' => 'string',
                        'description' => 'Nomor telepon penerima.',
                    ],
                    'shipping_address' => [
                        'type' => 'string',
                        'description' => 'Alamat lengkap pengiriman.',
                    ]
                ],
                ['customer_name', 'customer_phone', 'shipping_address']
            );
        }

        if ($admin) {
            $tools[] = $this->tool('low_stock', 'Daftar produk dengan stok menipis (stok <= threshold).', [
                'threshold' => ['type' => 'integer', 'description' => 'Batas stok (default '.ReportService::threshold().').'],
            ]);
            $tools[] = $this->tool('sales_report', 'Laporan penjualan periode tertentu: total omzet, jumlah order, item terjual, produk terlaris. Hanya order paid/shipped/completed.', [
                'from' => ['type' => 'string', 'description' => 'Tanggal mulai YYYY-MM-DD (default 30 hari lalu).'],
                'to' => ['type' => 'string', 'description' => 'Tanggal akhir YYYY-MM-DD (default hari ini).'],
                'limit' => ['type' => 'integer', 'description' => 'Jumlah produk terlaris (default 5).'],
            ]);
            $tools[] = $this->tool('recent_orders', 'Pesanan terbaru dan jumlah pesanan per status dalam N hari terakhir.', [
                'limit' => ['type' => 'integer', 'description' => 'Jumlah pesanan (1-20, default 10).'],
                'status' => ['type' => 'string', 'enum' => array_keys(Order::STATUSES), 'description' => 'Filter status pesanan (opsional).'],
                'days' => ['type' => 'integer', 'description' => 'Periode ringkasan status dalam hari (default 7).'],
            ]);
        }

        return $tools;
    }

    /** Eksekusi satu tool call. Selalu mengembalikan array (error dikembalikan sebagai data, bukan exception). */
    public function execute(string $name, array $args, ?User $user): array
    {
        if (! in_array($name, $this->allowedNames($user), true)) {
            return ['error' => 'Akses ditolak: tool "'.$name.'" tidak tersedia untuk peran Anda. Hanya informasi produk yang bisa diberikan.'];
        }

        $publicOnly = ! self::isAdmin($user);

        return match ($name) {
            'search_products' => $this->searchProducts($args, $publicOnly),
            'get_product' => $this->getProduct($args, $publicOnly),
            'check_stock' => $this->checkStock($args, $publicOnly),
            'low_stock' => $this->lowStock($args),
            'sales_report' => $this->salesReport($args),
            'recent_orders' => $this->recentOrders($args),
            'add_product_to_cart' => $this->addProductToCart($args),
            'checkout_order' => $this->checkoutOrder($args, $user),
            'remove_product_from_cart' => $this->removeProductFromCart($args, $user),
            'update_cart_quantity' => $this->updateCartQuantity($args, $user),
        };
    }

    private function updateCartQuantity(array $args, ?User $user): array
    {
        if (! $user || self::isAdmin($user)) {
            return [
                'success' => false,
                'message' => 'Fitur ini hanya tersedia untuk pelanggan yang login.',
            ];
        }

        $validator = Validator::make($args, [
            'product_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return [
                'success' => false,
                'message' => 'ID produk atau jumlah produk tidak valid.',
                'errors' => $validator->errors()->toArray(),
            ];
        }

        $data = $validator->validated();
        $productId = (int) $data['product_id'];
        $quantity = (int) $data['quantity'];

        $cart = session()->get('cart', []);

        if (! is_array($cart)) {
            $cart = [];
        }

        if (! array_key_exists($productId, $cart)) {
            return [
                'success' => false,
                'message' => 'Produk tidak ditemukan di keranjang.',
                'debug' => [
                    'requested_product_id' => $productId,
                    'cart_product_ids' => array_keys($cart),
                ],
            ];
        }

        $product = Product::query()->find($productId);

        if (! $product || ! $product->isActive()) {
            return [
                'success' => false,
                'message' => 'Produk tidak tersedia atau sudah tidak aktif.',
            ];
        }

        if ($quantity > (int) $product->stock) {
            return [
                'success' => false,
                'message' => "Stok {$product->name} hanya tersedia {$product->stock} unit.",
            ];
        }

        $cart[$productId] = $quantity;

        session()->put('cart', $cart);

        return [
            'success' => true,
            'message' => "Jumlah {$product->name} berhasil diubah.",
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'old_quantity' => (int) ($cart[$productId] ?? $quantity),
                'quantity' => (int) $cart[$productId],
                'unit_price' => (int) $product->price,
                'subtotal' => (int) $product->price * $quantity,
            ],
        ];
    }

    private function removeProductFromCart(array $args, ?User $user): array
    {
        if (! $user || $this->isAdmin($user)) {
            return [
                'success' => false,
                'message' => 'Fitur ini hanya tersedia untuk pelanggan yang login.',
            ];
        }

        $data = Validator::make($args, [
            'product_id' => ['required', 'integer', 'min:1'],
        ])->validate();

        $productId = (int) $data['product_id'];
        $cart = session('cart', []);

        if (! array_key_exists($productId, $cart)) {
            return [
                'success' => false,
                'message' => 'Produk tersebut tidak ditemukan di keranjang.',
            ];
        }

        unset($cart[$productId]);
        session(['cart' => $cart]);

        return [
            'success' => true,
            'message' => 'Produk berhasil dihapus dari keranjang.',
            'cart_item_count' => array_sum($cart),
        ];
    }

    private function checkoutOrder(array $args, ?User $user): array
    {
        if (!$user || self::isAdmin($user)) {
            return [
                'success' => false,
                'error' => 'Checkout hanya tersedia untuk customer yang login.',
            ];
        }

        $validator = Validator::make($args, [
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:30'],
            'shipping_address' => ['required', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return [
                'success' => false,
                'error' => 'Data checkout tidak valid.',
                'details' => $validator->errors()->toArray(),
            ];
        }

        $cart = session('cart', []);

        if (!is_array($cart) || empty($cart)) {
            return [
                'success' => false,
                'error' => 'Keranjang masih kosong.',
            ];
        }

        $data = $validator->validated();

        try {
            $result = DB::transaction(function () use ($user, $data, $cart) {
                $products = [];
                $total = 0;

                foreach ($cart as $productId => $quantity) {
                    if (
                        ! is_numeric($productId)
                        || (int) $productId < 1
                        || ! is_numeric($quantity)
                        || (int) $quantity < 1
                    ) {
                        return [
                            'error' => 'Data keranjang tidak valid. Periksa kembali keranjang Anda.',
                        ];
                    }

                    $product = Product::query()
                        ->whereKey((int) $productId)
                        ->lockForUpdate()
                        ->first();

                    if (!$product || !$product->isActive()) {
                        return [
                            'error' => 'Ada produk di keranjang yang sudah tidak tersedia. Periksa keranjang Anda.',
                        ];
                    }

                    $quantity = (int) $quantity;

                    if ($product->stock < $quantity) {
                        return [
                            'error' => "Stok {$product->name} tidak mencukupi. Stok tersedia: {$product->stock}.",
                        ];
                    }

                    $subtotal = (int) $product->price * $quantity;
                    $total += $subtotal;

                    $products[] = [
                        'product' => $product,
                        'quantity' => $quantity,
                        'subtotal' => $subtotal,
                    ];
                }

                if (empty($products)) {
                    return ['error' => 'Keranjang masih kosong.'];
                }

                $order = $user->orders()->create([
                    'order_number' => Order::generateNumber(),
                    'status' => Order::STATUS_PENDING,
                    'total_amount' => $total,
                    'customer_name' => $data['customer_name'],
                    'customer_phone' => $data['customer_phone'],
                    'shipping_address' => $data['shipping_address'],
                    'notes' => $data['notes'] ?? null,
                ]);

                foreach ($products as $item) {
                    $product = $item['product'];

                    $order->items()->create([
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'price' => (int) $product->price,
                        'quantity' => $item['quantity'],
                        'subtotal' => $item['subtotal'],
                    ]);
                }

                return ['order' => $order];
            });
        } catch (Throwable $e) {
            Log::error('Chatbot checkout gagal membuat order', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Pesanan gagal dibuat karena terjadi kesalahan. Silakan coba kembali.',
            ];
        }

        if (isset($result['error'])) {
            return [
                'success' => false,
                'error' => $result['error'],
            ];
        }

        /** @var Order $order */
        $order = $result['order'];

        // Samakan perilaku dengan CheckoutController yang sudah ada.
        session()->forget('cart');

        return [
            'success' => true,
            'message' => 'Pesanan berhasil dibuat dan menunggu konfirmasi pembayaran dari admin.',
            'order' => [
                'order_number' => $order->order_number,
                'status' => $order->status,
                'total_amount' => (int) $order->total_amount,
                'payment_status' => 'Menunggu konfirmasi admin',
                'order_url' => route('orders.show', $order),
            ],
        ];
    }


    private function addProductToCart(array $args): array
    {
        $validator = Validator::make($args, [
            'product_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['required', 'integer', 'min:1']
        ]);

        if($validator->fails()) {
            return [
                'success' => false,
                'error' => 'ID Produk atau jumlah produk tidak valid'
            ];
        }

        $productId = (int) $args['product_id'];
        $quantity = (int) $args['quantity'];

        $product = Product::query()->find($productId);

        if(!$product || !$product->isActive()) {
            return [
                'success' => false,
                'error' => 'Produk tidak ditemukan atau tidak tersedia.'
            ];
        }

        if($product->stock < 1) {
            return [
                'success' => false,
                'error' => 'Stok produk habis',
                'product' => $product->name
            ];
        }

        // Menggunakan session cart yang sama dengan CartController
        $cart = session('cart', []);
        if(!is_array($cart)) {
            $cart = [];
        }

        $currentQuantity = (int) ($cart[$productId] ?? 0);

        // Jangan sampai jumlah di keranjang melebih stok
        $newQuantity = min($currentQuantity + $quantity, (int) $product->stock);

        $addedQuantity = $newQuantity - $currentQuantity;

        if($addedQuantity < 1) {
            return [
                'success' => false,
                'error' => 'Jumlah produk di keranjang sudah mencapai batas stok.',
                'product' => $product->name,
                'cart_quantity' => $currentQuantity,
                'available_stock' => (int) $product->stock,
            ];
        }

        $cart[$productId] = $newQuantity;
        session()->put('cart', $cart);

        return [
            'success' => true,
            'message' => 'Produk berhasil ditambahkan ke keranjang.',
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'unit_price' => (int) $product->price,
            ],
            'quantity_added' => $addedQuantity,
            'cart_quantity' => $newQuantity,
            'subtotal' => (int) $product->price * $newQuantity,
        ];
    }

    private function searchProducts(array $args, bool $publicOnly): array
    {
        $status = $args['status'] ?? 'all';
        $filters = [
            'search' => is_string($args['query'] ?? null) ? mb_substr($args['query'], 0, 100) : null,
            'status' => in_array($status, [Product::STATUS_ACTIVE, Product::STATUS_INACTIVE], true) ? $status : null,
        ];
        $limit = $this->int($args['limit'] ?? null, 10, 1, 20);
        $query = $this->catalog->query($filters, $publicOnly);

        return [
            'total_found' => (clone $query)->count(),
            'products' => $query->limit($limit)->get()->map(fn ($p) => CatalogService::summary($p))->all(),
        ];
    }

    private function getProduct(array $args, bool $publicOnly): array
    {
        $product = $this->catalog->find((int) ($args['product_id'] ?? 0), $publicOnly);

        return $product
            ? ['product' => CatalogService::summary($product, withDescription: true)]
            : ['error' => 'Produk tidak ditemukan.'];
    }

    private function checkStock(array $args, bool $publicOnly): array
    {
        $id = (int) ($args['product_id'] ?? 0) ?: null;
        $name = is_string($args['name'] ?? null) ? mb_substr(trim($args['name']), 0, 100) : null;
        if (! $id && ! $name) {
            return ['error' => 'Isi product_id atau name.'];
        }

        $products = $this->catalog->stockCheck($id, $name, $publicOnly);

        return $products->isEmpty()
            ? ['error' => 'Produk tidak ditemukan.']
            : ['products' => $products->map(fn ($p) => CatalogService::summary($p))->all()];
    }

    private function lowStock(array $args): array
    {
        $threshold = $this->int($args['threshold'] ?? null, ReportService::threshold(), 0, 100000);

        return [
            'threshold' => $threshold,
            'products' => $this->reports->lowStock($threshold)->map(fn ($p) => CatalogService::stockRow($p))->all(),
        ];
    }

    private function salesReport(array $args): array
    {
        $validator = Validator::make($args, [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        if ($validator->fails()) {
            return ['error' => 'Format tanggal harus YYYY-MM-DD dan "to" >= "from".'];
        }

        return $this->reports->salesReport($args['from'] ?? null, $args['to'] ?? null, $this->int($args['limit'] ?? null, 5, 1, 20));
    }

    private function recentOrders(array $args): array
    {
        $status = Validator::make($args, ['status' => ['nullable', Rule::in(array_keys(Order::STATUSES))]])->fails()
            ? null : ($args['status'] ?? null);

        return $this->reports->recentOrders(
            $this->int($args['limit'] ?? null, 10, 1, 20),
            $status,
            $this->int($args['days'] ?? null, 7, 1, 365),
        );
    }

    private function tool(string $name, string $description, array $properties, array $required = []): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $name,
                'description' => $description,
                'parameters' => array_filter([
                    'type' => 'object',
                    'properties' => (object) $properties,
                    'required' => $required ?: null,
                ]),
            ],
        ];
    }

    private function int(mixed $value, int $default, int $min, int $max): int
    {
        return is_numeric($value) ? max($min, min($max, (int) $value)) : $default;
    }
}
