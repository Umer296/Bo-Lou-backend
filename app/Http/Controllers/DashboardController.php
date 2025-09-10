<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{

    public function index(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate   = $request->input('end_date');

        // Normalize dates (if provided)
        if ($startDate && $endDate) {
            $startDate = Carbon::parse($startDate)->startOfDay();
            $endDate   = Carbon::parse($endDate)->endOfDay();
        }

        // Orders
        $ordersQuery = Order::query();
        if ($startDate && $endDate) {
            $ordersQuery->whereBetween('created_at', [$startDate, $endDate]);
        }

        $totalOrdersCount = $ordersQuery->count();
        $totalOrdersPrice = (int) $ordersQuery->sum('total_price');

        // Shipments (via OrderItems)
        $shipmentsQuery = Shipment::query();
        if ($startDate && $endDate) {
            $shipmentsQuery->whereHas('orderItems.order', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('created_at', [$startDate, $endDate]);
            });
        }

        $totalShipmentsCount = $shipmentsQuery->count();
        $totalShipmentsPrice = (int) $shipmentsQuery->sum('price');

        // Gross Profit (Orders - Shipments)
        $grossProfit = $totalOrdersPrice - $totalShipmentsPrice;

        return response()->json([
            'orders_count'     => $totalOrdersCount,
            'orders_price'     => $totalOrdersPrice,
            'shipments_count'  => $totalShipmentsCount,
            'shipments_price'  => $totalShipmentsPrice,
            'gross_profit'     => $grossProfit,
            'date_range' => [
                'start' => $startDate,
                'end'   => $endDate,
            ],
        ]);
    }

}
