<?php

namespace App\Http\Controllers;

use App\Models\OrderItem;
use Illuminate\Http\Request;

class OrderItemController extends Controller
{
    /**
     * Display a listing of order items.
     */
    public function index(Request $request)
    {
        $brand = $request->get('brand');
        $limit = $request->get('limit', 10);
    
        $orderItems = OrderItem::with(['order.customer', 'product.images', 'shipment', 'variant'])
            ->whereNull('shipment_id')
            ->when($brand, function ($query, $brand) {
                $query->whereHas('product', function ($q) use ($brand) {
                    $q->where('brand', $brand);
                });
            })
            ->latest()
            ->paginate($limit);
    
        // Transform output
        $data = $orderItems->through(function ($item) {
            // generate image URLs
            $images = [];
            if ($item->product && $item->product->images) {
                foreach ($item->product->images as $img) {
                    $images[] = [
                        'id'    => $img->id,
                        'image' => $img->image_path,
                    ];
                }
            }
    
            return [
                'id'               => $item->id,
                'order_id'         => $item->order_id,
                'product_quantity' => $item->product_quantity,
                'variant_id'       => $item->variant_id,
                'variant'          => $item->variant,   // full variant details
                'product'          => [
                    'id'     => $item->product->id,
                    'name'   => $item->product->name,
                    'brand'  => $item->product->brand,
                    'images' => $images,
                ],
            ];
        });
    
        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $orderItems->currentPage(),
                'per_page'     => $orderItems->perPage(),
                'total'        => $orderItems->total(),
                'last_page'    => $orderItems->lastPage(),
            ]
        ]);
    }
    
}
