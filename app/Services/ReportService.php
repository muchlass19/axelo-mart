<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
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

    /**
     * Laporan penjualan lengkap untuk periode (format sama dengan API /reports/sales).
     * Default: 30 hari terakhir. Tanggal format Y-m-d.
     */
    public function salesReport(?string $from = null, ?string $to = null, int $limit = 5): array
    {
        $toDate = $to ? Carbon::parse($to) : today();
        $fromDate = $from ? Carbon::parse($from) : $toDate->copy()->subDays(29);
        $summary = $this->salesSummary($fromDate, $toDate);

        return [
            'period' => ['from' => $fromDate->toDateString(), 'to' => $toDate->toDateString()],
            'counted_statuses' => Order::SALES_STATUSES,
            ...$summary,
            'total_revenue_formatted' => rupiah($summary['total_revenue']),
            'top_products' => $this->topProducts($fromDate, $toDate, $limit),
        ];
    }

    /** Ringkasan order terbaru + jumlah order per status dalam N hari terakhir. */
    public function recentOrders(int $limit = 10, ?string $status = null, int $days = 7): array
    {
        $statusCounts = Order::where('created_at', '>=', now()->subDays($days))
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($n) => (int) $n);

        $orders = Order::with('items')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest()
            ->limit($limit)
            ->get();

        return [
            'summary' => [
                'days' => $days,
                'total_orders' => $statusCounts->sum(),
                'by_status' => $statusCounts,
            ],
            'data' => $orders->map(fn (Order $order) => [
                'order_number' => $order->order_number,
                'customer_name' => $order->customer_name,
                'status' => $order->status,
                'status_label' => $order->statusLabel(),
                'total_amount' => $order->total_amount,
                'total_amount_formatted' => rupiah($order->total_amount),
                'items_count' => $order->items->sum('quantity'),
                'items' => $order->items->map(fn ($i) => ['product_name' => $i->product_name, 'quantity' => $i->quantity, 'subtotal' => $i->subtotal]),
                'payment_type' => $order->payment_type,
                'created_at' => $order->created_at->toIso8601String(),
                'paid_at' => $order->paid_at?->toIso8601String(),
            ])->all(),
        ];
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
