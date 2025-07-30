<?php

// app/Console/Commands/FetchShopifyProducts.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ShopifyProductService;

class FetchShopifyProducts extends Command
{
    protected $signature = 'shopify:fetch-products';
    protected $description = 'Fetch and save Shopify products';
    protected $shopify;

    public function __construct(ShopifyProductService $shopify)
    {
        parent::__construct();
        $this->shopify = $shopify;
    }

    public function handle()
    {
        $this->info("Fetching Shopify products...");
        $this->shopify->fetchAndStoreProducts();
        $this->info("Fetch complete.");
    }
}
