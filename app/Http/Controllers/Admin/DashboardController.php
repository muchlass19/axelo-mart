<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Services\ReportService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(ReportService $reports): View
    {
        return view('admin.dashboard', [
            'allTime' => $reports->salesSummary(),
            'last30Days' => $reports->salesSummary(now()->subDays(29), now()),
            'pendingOrders' => Order::where('status', Order::STATUS_PENDING)->count(),
            'productCount' => Product::count(),
            'bestSellers' => $reports->topProducts(limit: 5),
            'lowStock' => $reports->lowStock(),
            'threshold' => ReportService::threshold(),
            'recentOrders' => Order::latest()->limit(5)->get(),
        ]);
    }
}
