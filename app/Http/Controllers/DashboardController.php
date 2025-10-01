<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Shipment;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{

    public function index(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate   = $request->input('end_date');

        if ($startDate && $endDate) {
            $startDate = Carbon::parse($startDate)->startOfDay();
            $endDate   = Carbon::parse($endDate)->endOfDay();
        }

        // Get only order items that are linked to shipments
        $orderItemsQuery = OrderItem::with(['order', 'shipment', 'variant'])
            ->whereNotNull('shipment_id');

        // Apply date filter using Order's created_at
        if ($startDate && $endDate) {
            $orderItemsQuery->whereHas('order', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('created_at', [$startDate, $endDate]);
            });
        }

        $orderItems = $orderItemsQuery->get();

        // Total Orders (unique order IDs)
        $totalOrders = $orderItems->pluck('order_id')->unique()->count();

        // Total Shipments (unique shipment IDs)
        $totalShipments = $orderItems->pluck('shipment_id')->unique()->count();

        // Total Selling Price & Cost Price
        $totalSellingPrice = 0;
        $totalCostPrice = 0;

        foreach ($orderItems as $item) {
            $quantity = $item->product_quantity;
            $variant = $item->variant;

            if ($variant) {
                $totalSellingPrice += $quantity * (float) $variant->product_price;
                $totalCostPrice += $quantity * (float) $variant->product_cost;
            }
        }

        // Shipment Charges
        $shipmentCharges = $orderItems
            ->pluck('shipment')
            ->filter()
            ->unique('id')
            ->sum('shipment_charges');

        // Profit Calculations
        $grossProfit = $totalSellingPrice - $totalCostPrice;
        $netProfit = $grossProfit - $shipmentCharges;
        $totalProducts = $orderItems->sum('product_quantity');

        return response()->json([
            'total_orders'          => $totalOrders,
            'total_shipments'       => $totalShipments,
            'total_products'         => $totalProducts,
            'total_selling_price'   => $totalSellingPrice,
            'total_cost_price'      => $totalCostPrice,
            'gross_profit'          => $grossProfit,
            'total_shipment_charges'=> $shipmentCharges,
            'net_profit'            => $netProfit,
            'date_range' => [
                'start' => $startDate,
                'end'   => $endDate,
            ],
        ]);
    }

}
