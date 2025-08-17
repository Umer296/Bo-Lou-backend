<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'shipment_id',
        'product_quantity',
        'variant_id', // <-- added
    ];

    /**
     * OrderItem belongs to an Order
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * OrderItem belongs to a Product
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * OrderItem belongs to a Shipment
     */
    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }

    /**
     * OrderItem belongs to a Product Variant
     */
    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}
