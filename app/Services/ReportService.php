<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Laporan penjualan. Hanya order berstatus paid/shipped/completed yang dihitung,
 * dan periode dihitung berdasarkan tanggal bayar (paid_at).
 */
class ReportService
{
    public function salesSummary(?CarbonInterface $from = null, ?CarbonInterface $to = null): array
    {
        $orders = $this->salesOrders($from, $to);

        $totalOrders = (clone $orders)->count();
        $totalRevenue = (int) (clone $orders)->sum('total_amount');
        $itemsSold = (int) OrderItem::whereIn('order_id', (clone $orders)->select('id'))->sum('quantity');

        return [
            'total_revenue' => $totalRevenue,
            'total_orders' => $totalOrders,
            'items_sold' => $itemsSold,
            'average_order_value' => $totalOrders ? (int) round($totalRevenue / $totalOrders) : 0,
        ];
    }

    public function topProducts(?CarbonInterface $from = null, ?CarbonInterface $to = null, int $limit = 5): Collection
    {
        return OrderItem::query()
            ->whereIn('order_id', $this->salesOrders($from, $to)->select('id'))
            ->selectRaw('product_id, MAX(product_name) as product_name, SUM(quantity) as quantity_sold, SUM(subtotal) as revenue')
            ->groupBy('product_id')
            ->orderByDesc('quantity_sold')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'product_id' => $row->product_id,
                'product_name' => $row->product_name,
                'quantity_sold' => (int) $row->quantity_sold,
                'revenue' => (int) $row->revenue,
            ]);
    }

    public function lowStock(?int $threshold = null): Collection
    {
        return Product::where('stock', '<=', $threshold ?? self::threshold())
            ->orderBy('stock')
            ->orderBy('name')
            ->get();
    }

    public static function threshold(): int
    {
        return (int) config('axelo.low_stock_threshold', 5);
    }

    private function salesOrders(?CarbonInterface $from, ?CarbonInterface $to): Builder
    {
        return Order::query()->sales()
            ->when($from, fn ($q) => $q->where('paid_at', '>=', $from->copy()->startOfDay()))
            ->when($to, fn ($q) => $q->where('paid_at', '<=', $to->copy()->endOfDay()));
    }
}
