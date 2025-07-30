<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Models\ShopifyProduct;

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
                ShopifyProduct::updateOrCreate(
                    ['shopify_id' => $product['id']],
                    [
                        'title' => $product['title'],
                        'body_html' => $product['body_html'],
                        'vendor' => $product['vendor'],
                        'product_type' => $product['product_type'],
                        'handle' => $product['handle'],
                        'images' => json_encode($product['images']),
                        'variants' => json_encode($product['variants']),
                    ]
                );
            }

            $linkHeader = $response->header('Link');
            if ($linkHeader && preg_match('/<([^>]+)>; rel="next"/', $linkHeader, $matches)) {
                $url = $matches[1];
            } else {
                $url = null;
            }

        } while ($url);
    }
}
