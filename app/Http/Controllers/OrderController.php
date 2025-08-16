<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Customer;
use App\Models\Product;
use App\Models\OrderItem;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    // List all orders
    public function index()
    {
        $limit = request()->get('limit', 10);
        $status = request()->get('status');
        $brand = request()->get('brand');

        $query = Order::with([
            'customer',
            'orderItems.product', // load product inside orderItems
            'shipment'
        ]);

        if ($status) {
            $query->where('status', $status);
        }

        if ($brand) {
            $query->whereHas('orderItems.product', function ($q) use ($brand) {
                $q->where('brand', 'like', "%{$brand}%");
            });
        }

        return $query->orderBy('created_at', 'desc')->paginate($limit);
    }

    // Store a new order
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'customer_name' => 'required|string',
                'customer_address' => 'required|string',
                'customer_city' => 'required|string',
                'customer_phone_number' => 'required|string',
                'customer_email' => 'required|email',
                'customer_payment_method' => 'required|string',

                'delivery_time' => 'nullable|date',
                'total_price' => 'required|numeric|min:0',
                'products' => 'required|array|min:1',
                'products.*.id' => 'required|exists:products,id',
                'products.*.quantity' => 'required|integer|min:1',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }

        // Create or find customer
        $customer = Customer::firstOrCreate(
            ['email' => $validated['customer_email']],
            [
                'name' => $validated['customer_name'],
                'address' => $validated['customer_address'],
                'city' => $validated['customer_city'],
                'phone_number' => $validated['customer_phone_number'],
                'payment_method' => $validated['customer_payment_method'],
            ]
        );

        // Format delivery time
        $deliveryTime = $validated['delivery_time'] 
            ? \Carbon\Carbon::parse($validated['delivery_time'])->format('Y-m-d H:i:s') 
            : null;

        // Create order
        $order = Order::create([
            'customer_id' => $customer->id,
            'delivery_time' => $deliveryTime,
            'total_price' => $validated['total_price'],
            'status' => 'Pending',
        ]);

        // Insert order items
        foreach ($validated['products'] as $item) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $item['id'],
                'product_quantity' => $item['quantity'],
            ]);
        }

        return response()->json([
            'message' => 'Order created successfully',
            'order' => $order,
        ], 201);
    }

    // Show a specific order
    public function show(Order $order)
    {
        return $order->load([
            'customer',
            'orderItems.product', // products through orderItems
            'shipment'
        ]);
    }

    // Update an order
    public function update(Request $request, $order_id)
    {
        $order = Order::with(['customer', 'orderItems.product'])->findOrFail($order_id);

        try {
            $validated = $request->validate([
                // Customer fields
                'customer_name' => 'required|string',
                'customer_address' => 'required|string',
                'customer_city' => 'required|string',
                'customer_phone_number' => 'required|string',
                'customer_email' => 'required|email',
                'customer_payment_method' => 'required|string',

                // Product fields (assuming only updating 1st product in order)
                'product_name' => 'required|string',
                'product_description' => 'nullable|string',
                'product_price' => 'required|numeric',
                'product_quantity' => 'required|integer',
                'brand' => 'required|string',

                // Order fields
                'delivery_time' => 'nullable|date',
                'status' => 'in:Pending,In Progress,Cancelled,Completed',
                'total_price' => 'required|numeric|min:0', // <-- added
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }

        // Update Customer
        $order->customer->update([
            'name' => $validated['customer_name'],
            'address' => $validated['customer_address'],
            'city' => $validated['customer_city'],
            'phone_number' => $validated['customer_phone_number'],
            'email' => $validated['customer_email'],
            'payment_method' => $validated['customer_payment_method'],
        ]);

        // Update first product in orderItems
        if ($order->orderItems->isNotEmpty()) {
            $orderItem = $order->orderItems->first();

            $orderItem->product->update([
                'name' => $validated['product_name'],
                'description' => $validated['product_description'],
                'price' => $validated['product_price'],
                'brand' => $validated['brand'],
            ]);

            // Update pivot quantity
            $order->products()->updateExistingPivot($orderItem->product_id, [
                'product_quantity' => $validated['product_quantity']
            ]);
        }

        // Update Order
        $deliveryTime = $validated['delivery_time']
            ? \Carbon\Carbon::parse($validated['delivery_time'])->format('Y-m-d H:i:s')
            : null;

        $order->update([
            'delivery_time' => $deliveryTime,
            'status' => $validated['status'],
            'total_price' => $validated['total_price'], // <-- set directly from request
        ]);

        return response()->json([
            'message' => 'Order and related data updated',
            'order' => $order->fresh(['customer', 'orderItems.product', 'shipment'])
        ]);
    }

    // Delete an order
    public function destroy(Order $order)
    {
        // Delete shipment if exists
        if ($order->shipment) {
            $order->shipment->delete();
        }

        // Delete related products and pivot records
        if ($order->products()->exists()) {
            // Detach products from pivot table (order_items)
            $order->products()->detach();
        }

        // Delete customer if exists
        if ($order->customer) {
            $order->customer->delete();
        }

        // Finally delete the order itself
        $order->delete();

        return response()->json(['message' => 'Order and related data deleted successfully']);
    }

}
