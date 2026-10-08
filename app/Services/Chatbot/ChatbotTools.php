<?php

namespace App\Services\Chatbot;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CatalogService;
use App\Services\ReportService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

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
    public const PUBLIC_TOOLS = ['search_products', 'get_product', 'check_stock'];

    public const ADMIN_TOOLS = [...self::PUBLIC_TOOLS, 'low_stock', 'sales_report', 'recent_orders'];

    public function __construct(private CatalogService $catalog, private ReportService $reports) {}

    public static function isAdmin(?User $user): bool
    {
        return (bool) $user?->isAdmin();
    }

    /** @return list<string> */
    public function allowedNames(?User $user): array
    {
        return self::isAdmin($user) ? self::ADMIN_TOOLS : self::PUBLIC_TOOLS;
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
        ];

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
        };
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
