<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'customer_id', 'product_id', 'product_quantity', 'delivery_time', 'shipment_id', 'status',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'order_items')
                    ->withPivot('product_quantity')
                    ->withTimestamps();
    }

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }
}
