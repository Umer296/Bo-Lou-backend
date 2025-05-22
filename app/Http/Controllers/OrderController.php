<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    // List all orders
    public function index()
    {
        $limit = request()->get('limit', 10); // Default to 10 if not provided
        return Order::with(['customer', 'product', 'shipment'])->paginate($limit);
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
    
                'product_name' => 'required|string',
                'product_description' => 'nullable|string',
                'product_price' => 'required|numeric',
                'product_quantity' => 'required|integer',
                'product_category' => 'required|string',
    
                'delivery_time' => 'nullable|date',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    
        // Create or find customer
        $customer = Customer::create([
            'name' => $validated['customer_name'],
            'address' => $validated['customer_address'],
            'city' => $validated['customer_city'],
            'phone_number' => $validated['customer_phone_number'],
            'email' => $validated['customer_email'],
            'payment_method' => $validated['customer_payment_method'],
        ]);
    
        // Create product
        $product = Product::create([
            'name' => $validated['product_name'],
            'description' => $validated['product_description'],
            'price' => $validated['product_price'],
            'category' => $validated['product_category'],
        ]);
    
        // Create order
        $deliveryTime = $validated['delivery_time'] 
        ? \Carbon\Carbon::parse($validated['delivery_time'])->format('Y-m-d H:i:s') 
        : null;

        $order = Order::create([
            'customer_id' => $customer->id,
            'product_id' => $product->id,
            'product_quantity' => $validated['product_quantity'],
            'delivery_time' => $deliveryTime,
            'status' => 'Pending',
        ]);
    
        return response()->json(['message' => 'Order created successfully', 'order' => $order], 201);
    }    

    // Show a specific order
    public function show(Order $order)
    {
        return $order->load(['customer', 'product', 'shipment']);
    }

    // Update an order
    public function update(Request $request, $order_id)
    {
        $order = Order::with(['customer', 'product'])->findOrFail($order_id);

        try {
            $validated = $request->validate([
                // Customer fields
                'customer_name' => 'required|string',
                'customer_address' => 'required|string',
                'customer_city' => 'required|string',
                'customer_phone_number' => 'required|string',
                'customer_email' => 'required|email',
                'customer_payment_method' => 'required|string',

                // Product fields
                'product_name' => 'required|string',
                'product_description' => 'nullable|string',
                'product_price' => 'required|numeric',
                'product_quantity' => 'required|integer',
                'product_category' => 'required|string',

                // Order fields
                'delivery_time' => 'nullable|date',
                'status' => 'in:Pending,In Progress,Cancelled,Completed',
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

        // Update Product
        $order->product->update([
            'name' => $validated['product_name'],
            'description' => $validated['product_description'],
            'price' => $validated['product_price'],
            'category' => $validated['product_category'],
        ]);

        // Update Order
        $deliveryTime = $validated['delivery_time']
            ? \Carbon\Carbon::parse($validated['delivery_time'])->format('Y-m-d H:i:s')
            : null;

        $order->update([
            'product_quantity' => $validated['product_quantity'],
            'delivery_time' => $deliveryTime,
            'status' => $validated['status'],
        ]);

        return response()->json(['message' => 'Order and related data updated', 'order' => $order]);
    }

    // Delete an order
    public function destroy(Order $order)
    {
        if ($order->shipment) {
            $order->shipment->delete();
        }
    
        if ($order->product) {
            $order->product->delete();
        }
    
        if ($order->customer) {
            $order->customer->delete();
        }
        $order->delete();
    
        return response()->json(['message' => 'Order has been deleted']);
    }    
}
