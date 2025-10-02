<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductImage;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $products = Product::with(['variants', 'images'])
            ->when($request->brand, function ($query) use ($request) {
                $query->where('brand', 'like', '%' . $request->brand . '%');
            })
            ->when($request->search, function ($query) use ($request) {
                $query->where('name', 'like', '%' . $request->search . '%');
            })
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json($products);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            // 1️⃣ Create product
            $product = Product::create([
                'name'        => $request->name,
                'description' => $request->description,
                'brand'       => $request->brand,
            ]);

            // 2️⃣ Save images from Base64 array
            if ($request->has('images') && is_array($request->images)) {
                foreach ($request->images as $index => $base64Image) {
                    // Match the file extension from base64 string
                    if (preg_match('/^data:image\/(\w+);base64,/', $base64Image, $type)) {
                        $extension = strtolower($type[1]); // jpg, png, gif
                        $base64Image = substr($base64Image, strpos($base64Image, ',') + 1);
                        $base64Image = base64_decode($base64Image);

                        if ($base64Image === false) {
                            throw new \Exception('Base64 decode failed');
                        }

                        // Create unique file name
                        $fileName = time() . '_' . uniqid() . '.' . $extension;
                        $filePath = public_path('uploads/products/' . $fileName);

                        // Ensure directory exists
                        if (!file_exists(public_path('uploads/products'))) {
                            mkdir(public_path('uploads/products'), 0777, true);
                        }

                        // Save file
                        file_put_contents($filePath, $base64Image);

                        // Store in DB
                        ProductImage::create([
                            'product_id' => $product->id,
                            'image_path' => url('uploads/products/' . $fileName),
                            'is_main'    => $index === 0, // first image as main
                        ]);
                    }
                }
            }

            // 3️⃣ Store variants
            if ($request->has('variants') && is_array($request->variants)) {
                foreach ($request->variants as $variantData) {
                    ProductVariant::create([
                        'product_id' => $product->id,
                        'name'       => $variantData['name'] ?? null,
                        'product_price' => $variantData['product_price'] ?? null,
                        'product_cost' => $variantData['product_cost'] ?? null,
                        'attributes' => $variantData['attributes'] ?? [],
                    ]);
                }
            }

            return response()->json([
                'message' => 'Product created successfully',
                'product' => $product->load('variants', 'images'),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Failed to create product',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $product = Product::with(['variants', 'images'])->findOrFail($id);
    
        // Convert images to base64
        $product->images->transform(function ($image) {
            $path = public_path(str_replace(url('/'), '', $image->image_path));
    
            if (file_exists($path)) {
                $imageData = file_get_contents($path);
                $base64 = 'data:image/' . pathinfo($path, PATHINFO_EXTENSION) . ';base64,' . base64_encode($imageData);
                $image->image_path = $base64;
            } else {
                $image->image_path = $image->image_path; // or keep original path if you prefer
            }
    
            return $image;
        });
    
        return response()->json($product);
    }    

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Product $product)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        try {
            $product = Product::findOrFail($id);

            // 1️⃣ Update basic info
            $product->update([
                'name'        => $request->name ?? $product->name,
                'description' => $request->description ?? $product->description,
                'brand'       => $request->brand ?? $product->brand,
            ]);

            // 2️⃣ Update images if provided
            if ($request->has('images') && is_array($request->images)) {
                // Optional: delete old images
                foreach ($product->images as $oldImage) {
                    $oldPath = public_path(str_replace(url('/'), '', $oldImage->image_path));
                    if (file_exists($oldPath)) unlink($oldPath);
                    $oldImage->delete();
                }
            
                foreach ($request->images as $index => $image) {
                    // ✅ 1) If it's already a valid URL, save it directly without processing
                    if (filter_var($image, FILTER_VALIDATE_URL)) {
                        ProductImage::create([
                            'product_id' => $product->id,
                            'image_path' => $image, // Keep as-is
                            'is_main'    => $index === 0,
                        ]);
            
                        continue; // Move to the next image
                    }
            
                    // ✅ 2) Otherwise, treat it as Base64 and decode it
                    if (preg_match('/^data:image\/(\w+);base64,/', $image, $type)) {
                        $extension = strtolower($type[1]);
                        $imageData = substr($image, strpos($image, ',') + 1);
                        $imageData = base64_decode($imageData);
            
                        if ($imageData === false) {
                            throw new \Exception('Base64 decode failed');
                        }
            
                        $fileName = time() . '_' . uniqid() . '.' . $extension;
                        $uploadDir = public_path('uploads/products/');
            
                        if (!file_exists($uploadDir)) {
                            mkdir($uploadDir, 0777, true);
                        }
            
                        $filePath = $uploadDir . $fileName;
                        file_put_contents($filePath, $imageData);
            
                        ProductImage::create([
                            'product_id' => $product->id,
                            'image_path' => url('uploads/products/' . $fileName),
                            'is_main'    => $index === 0,
                        ]);
                    }
                }
            }            

            // 3️⃣ Update variants
            if ($request->has('variants') && is_array($request->variants)) {
                // Optional: remove old variants
                $product->variants()->delete();

                // Insert new variants
                foreach ($request->variants as $variantData) {
                    ProductVariant::create([
                        'product_id' => $product->id,
                        'name'       => $variantData['name'] ?? null,
                        'product_price' => $variantData['product_price'] ?? null,
                        'product_cost' => $variantData['product_cost'] ?? null,
                        'attributes' => $variantData['attributes'] ?? [],
                    ]);
                }
            }

            return response()->json([
                'message' => 'Product updated successfully',
                'product' => $product->load('variants', 'images'),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Failed to update product',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $product = Product::findOrFail($id);

            // Delete images from storage
            foreach ($product->images as $image) {
                $oldPath = public_path(str_replace(url('/'), '', $image->image_path));
                if (file_exists($oldPath)) unlink($oldPath);
                $image->delete();
            }

            // Delete variants
            $product->variants()->delete();

            // Delete product
            $product->delete();

            return response()->json([
                'message' => 'Product deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Failed to delete product',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
