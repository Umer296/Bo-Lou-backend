<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShopifyProduct extends Model
{
    use HasFactory;
    protected $fillable = [
        'shopify_id',
        'title',
        'body_html',
        'vendor',
        'product_type',
        'handle',
        'images',
        'variants',
    ];
}
