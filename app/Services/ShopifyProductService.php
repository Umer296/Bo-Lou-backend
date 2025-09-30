<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;

class ShopifyProductService
{
    protected $baseUrl;
    protected $version;
    protected $token;

    public function __construct()
    {
        $this->baseUrl = "https://" . env('SHOPIFY_STORE_URL');
        $this->version = env('SHOPIFY_API_VERSION');
        $this->token = env('SHOPIFY_ACCESS_TOKEN');
    }

    public function fetchAndStoreProducts()
    {
        $url = "{$this->baseUrl}/admin/api/{$this->version}/products.json?limit=250";

        do {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->token,
                'Content-Type' => 'application/json',
            ])->get($url);

            if ($response->failed()) {
                logger()->error("Failed Shopify fetch: " . $response->body());
                break;
            }

            $products = $response->json('products') ?? [];

            foreach ($products as $product) {
                // Create or update Product
                $dbProduct = Product::updateOrCreate(
                    ['name' => $product['title']], // you can also store shopify_id in a new column
                    [
                        'description' => $product['body_html'] ?? '',
                        'brand' => 'Shein',
                    ]
                );

                // Store product images
                if (!empty($product['images'])) {
                    foreach ($product['images'] as $index => $image) {
                        ProductImage::updateOrCreate(
                            [
                                'product_id' => $dbProduct->id,
                                'image_path' => $image['src'],
                                'product_variant_id' => null
                            ],
                            [
                                'is_main' => $index === 0
                            ]
                        );
                    }
                }

                // Store product variants
                if (!empty($product['variants'])) {
                    foreach ($product['variants'] as $variant) {
                        $dbVariant = ProductVariant::updateOrCreate(
                            [
                                'sku' => $variant['sku'] ?? null,
                                'product_id' => $dbProduct->id
                            ],
                            [
                                'name' => $variant['title'] ?? '',
                                'product_price' => $variant['price'] ?? 0,
                                'product_cost' => $variant['price'] ?? 0,
                                'stock' => $variant['inventory_quantity'] ?? 0,
                                'attributes' => [
                                    'option1' => $variant['option1'] ?? null,
                                    'option2' => $variant['option2'] ?? null,
                                    'option3' => $variant['option3'] ?? null
                                ]
                            ]
                        );

                        // Link variant-specific images if available
                        if (!empty($variant['image_id']) && !empty($product['images'])) {
                            $variantImage = collect($product['images'])->firstWhere('id', $variant['image_id']);
                            if ($variantImage) {
                                ProductImage::updateOrCreate(
                                    [
                                        'product_id' => $dbProduct->id,
                                        'product_variant_id' => $dbVariant->id,
                                        'image_path' => $variantImage['src']
                                    ],
                                    [
                                        'is_main' => true
                                    ]
                                );
                            }
                        }
                    }
                }
            }

            // Handle pagination
            $linkHeader = $response->header('Link');
            if ($linkHeader && preg_match('/<([^>]+)>; rel="next"/', $linkHeader, $matches)) {
                $url = $matches[1];
            } else {
                $url = null;
            }

        } while ($url);
    }
}
