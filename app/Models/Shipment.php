<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shipment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'brand',
        'shipment_quantity',
        'shipment_description',
        'arriving_time_date',
        'total_price_variant',
        'price',
    ];

    /**
     * Shipment has many OrderItems
     */
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }
}
