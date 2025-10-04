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
                'products.*.variant_id' => 'nullable|integer', // ✅ allow variant_id
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
                'variant_id' => $item['variant_id'] ?? null, // ✅ handle variant_id
                'product_quantity' => $item['quantity'],
            ]);
        }

        return response()->json([
            'message' => 'Order created successfully',
            'order' => $order->load('orderItems.product'), // ✅ return with products
        ], 201);
    }

    // Show a specific order
    public function show(Order $order)
    {
        $order->load([
            'customer',
            'orderItems.product',
            'orderItems.variant', // <-- load variant to access product_price
        ]);

        // Append product_price to each order item
        $order->orderItems->transform(function ($item) {
            $item->product_price = $item->variant->product_price ?? null;
            return $item;
        });

        return $order;
    }

    // Update an order
    public function update(Request $request, Order $order)
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
                'products.*.variant_id' => 'nullable|integer', // ✅ allow variant_id
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    
        // ✅ Update or create customer
        $customer = Customer::updateOrCreate(
            ['email' => $validated['customer_email']],
            [
                'name' => $validated['customer_name'],
                'address' => $validated['customer_address'],
                'city' => $validated['customer_city'],
                'phone_number' => $validated['customer_phone_number'],
                'payment_method' => $validated['customer_payment_method'],
            ]
        );
    
        // ✅ Format delivery time
        $deliveryTime = $validated['delivery_time'] 
            ? \Carbon\Carbon::parse($validated['delivery_time'])->format('Y-m-d H:i:s') 
            : null;
    
        // ✅ Update order details
        $order->update([
            'customer_id' => $customer->id,
            'delivery_time' => $deliveryTime,
            'total_price' => $validated['total_price'],
        ]);
    
        // ✅ Remove old order items
        $order->orderItems()->delete();
    
        // ✅ Insert new order items
        foreach ($validated['products'] as $item) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $item['id'],
                'variant_id' => $item['variant_id'] ?? null, // ✅ handle variant_id
                'product_quantity' => $item['quantity'],
            ]);
        }
    
        return response()->json([
            'message' => 'Order updated successfully',
            'order' => $order->load('orderItems.product'), // ✅ return with products
        ]);
    }
    

    // Delete an order
    public function destroy(Order $order)
    {
        // Delete shipment if exists
        if ($order->shipment) {
            $order->shipment->delete();
        }
    
        // Delete related products and pivot records (order_items)
        if ($order->orderItems()->exists()) {
            foreach ($order->orderItems as $orderItem) {
                // If you want to clear variant association explicitly
                $orderItem->variant_id = null;
                $orderItem->save();
    
                // Then delete the order_item record itself
                $orderItem->delete();
            }
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
