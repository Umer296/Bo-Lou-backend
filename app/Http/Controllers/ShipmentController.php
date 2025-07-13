<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Models\Order;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ShipmentController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'brand' => 'required|string',
            'product_quantity' => 'required|integer',
            'product_description' => 'nullable|string',
            'arriving_time_date' => 'required|date',
            'price' => 'required|numeric',
            'orders' => 'required|array',
            'orders.*' => 'exists:orders,id',
        ]);

        // Convert ISO date to MySQL-compatible format
        $validated['arriving_time_date'] = Carbon::parse($validated['arriving_time_date'])->format('Y-m-d H:i:s');

        // Create shipment
        $shipment = Shipment::create($validated);

        // Update orders
        Order::whereIn('id', $validated['orders'])->update([
            'shipment_id' => $shipment->id,
            'status' => 'In Progress',
        ]);

        return response()->json(['message' => 'Shipment created successfully', 'shipment' => $shipment]);
    }

    public function show($id)
    {
        $shipment = Shipment::with([
            'orders.customer',
            'orders.product',
            'orders.shipment'
        ])->findOrFail($id);
        return response()->json($shipment);
    }

    public function index(Request $request)
    {
        $limit = $request->get('limit', 10); // default 10 per page

        $shipments = Shipment::with([
            'orders.customer',
            'orders.product',
            'orders.shipment'
        ])
        ->orderBy('created_at', 'desc') // order by latest
        ->paginate($limit);

        return response()->json($shipments);
    }

    // ✏️ Update a shipment
    public function update(Request $request, $id)
    {
        $shipment = Shipment::findOrFail($id);

        $validated = $request->validate([
            'brand' => 'sometimes|required|string',
            'product_quantity' => 'sometimes|required|integer',
            'product_description' => 'nullable|string',
            'arriving_time_date' => 'sometimes|required|date',
            'price' => 'sometimes|required|numeric',
            'orders' => 'sometimes|required|array',
            'orders.*' => 'exists:orders,id',
        ]);

        $shipment->update($validated);

        // Optionally update orders if provided
        if (isset($validated['orders'])) {
            Order::whereIn('id', $validated['orders'])->update([
                'shipment_id' => $shipment->id,
                'status' => 'In Progress',
            ]);
        }

        return response()->json(['message' => 'Shipment updated', 'shipment' => $shipment]);
    }

    public function destroy($id)
    {
        $shipment = Shipment::findOrFail($id);

        // Update associated orders: remove shipment_id and reset status to "Pending"
        Order::where('shipment_id', $shipment->id)->update([
            'shipment_id' => null,
            'status' => 'Pending',
        ]);

        // Delete the shipment
        $shipment->delete();

        return response()->json(['message' => 'Shipment deleted and orders reverted to pending']);
    }

}
