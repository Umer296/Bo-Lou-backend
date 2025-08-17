<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ShipmentController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'brand'                => 'required|string',
            'shipment_quantity'    => 'required|integer',
            'shipment_description' => 'nullable|string',
            'arriving_time_date'   => 'required|date',
            'price'                => 'required|numeric',
            'total_price_variant'  => 'required|numeric',
            'order_items'          => 'required|array',
            'order_items.*'        => 'exists:order_items,id',
        ]);

        // Convert ISO date to MySQL-compatible format
        $validated['arriving_time_date'] = Carbon::parse($validated['arriving_time_date'])->format('Y-m-d H:i:s');

        // Create shipment
        $shipment = Shipment::create([
            'brand'                => $validated['brand'],
            'shipment_quantity'    => $validated['shipment_quantity'],
            'shipment_description' => $validated['shipment_description'] ?? null,
            'arriving_time_date'   => $validated['arriving_time_date'],
            'price'                => $validated['price'],
            'total_price_variant'  => $validated['total_price_variant'],
        ]);

        // Attach shipment to order_items
        OrderItem::whereIn('id', $validated['order_items'])->update([
            'shipment_id' => $shipment->id,
        ]);

        // Get unique order IDs from order_items
        $orderIds = OrderItem::whereIn('id', $validated['order_items'])
            ->pluck('order_id')
            ->unique()
            ->toArray();

        // Update those orders to "In Progress"
        Order::whereIn('id', $orderIds)->update([
            'status' => 'In Progress',
        ]);

        return response()->json([
            'message'   => 'Shipment created successfully',
            'shipment'  => $shipment,
            'order_ids' => $orderIds, // optional confirmation
        ]);
    }

    public function show($id)
    {
        $shipment = Shipment::with([
            'orderItems.order',            // order details
            'orderItems.product',          // product details
            'orderItems.variant'           // if you want variant details too
        ])->findOrFail($id);
    
        return response()->json($shipment);
    }
    
    public function index(Request $request)
    {
        $limit = $request->get('limit', 10); // default 10 per page
    
        $shipments = Shipment::with([
            'orderItems.order',
            'orderItems.product',
            'orderItems.variant'
        ])
        ->orderBy('created_at', 'desc')
        ->paginate($limit);
    
        return response()->json($shipments);
    }    

    // ✏️ Update a shipment
    public function update(Request $request, Shipment $shipment)
    {
        $validated = $request->validate([
            'brand'                => 'required|string',
            'shipment_quantity'    => 'required|integer',
            'shipment_description' => 'nullable|string',
            'arriving_time_date'   => 'required|date',
            'price'                => 'required|numeric',
            'total_price_variant'  => 'required|numeric',
            'order_items'          => 'required|array',
            'order_items.*'        => 'exists:order_items,id',
        ]);

        // Convert ISO date to MySQL-compatible format
        $validated['arriving_time_date'] = Carbon::parse(
            $validated['arriving_time_date']
        )->format('Y-m-d H:i:s');

        // Update shipment
        $shipment->update([
            'brand'                => $validated['brand'],
            'shipment_quantity'    => $validated['shipment_quantity'],
            'shipment_description' => $validated['shipment_description'] ?? null,
            'arriving_time_date'   => $validated['arriving_time_date'],
            'price'                => $validated['price'],
            'total_price_variant'  => $validated['total_price_variant'],
        ]);

        // Reset old order_items that belonged to this shipment
        OrderItem::where('shipment_id', $shipment->id)
            ->update(['shipment_id' => null]);

        // Attach updated order_items to this shipment
        OrderItem::whereIn('id', $validated['order_items'])
            ->update(['shipment_id' => $shipment->id]);

        // Extract unique order IDs from those order_items
        $orderIds = OrderItem::whereIn('id', $validated['order_items'])
            ->pluck('order_id')
            ->unique();

        // Update related orders
        Order::whereIn('id', $orderIds)->update(['status' => 'In Progress']);

        return response()->json([
            'message'   => 'Shipment updated successfully',
            'shipment'  => $shipment,
            'order_ids' => $orderIds->values(),
        ]);
    }

    public function destroy($id)
    {
        $shipment = Shipment::findOrFail($id);

        // Reset shipment_id for related order_items
        OrderItem::where('shipment_id', $shipment->id)->update([
            'shipment_id' => null,
        ]);

        // Get affected order IDs
        $orderIds = OrderItem::where('shipment_id', $shipment->id)->pluck('order_id')->unique();

        // Reset status of those orders back to "Pending"
        if ($orderIds->isNotEmpty()) {
            Order::whereIn('id', $orderIds)->update([
                'status' => 'Pending',
            ]);
        }

        // Delete the shipment
        $shipment->delete();

        return response()->json([
            'message'   => 'Shipment deleted and related orders reverted to Pending',
            'order_ids' => $orderIds->values()
        ]);
    }

}
