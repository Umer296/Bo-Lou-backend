<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ShopifyProduct;

class ShopifyProductsController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10); // default to 10 per page

        $products = ShopifyProduct::orderBy('id', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }
}
