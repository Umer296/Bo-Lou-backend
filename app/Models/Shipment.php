<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shipment extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'product_category', 'product_quantity', 'product_description', 'arriving_time_date', 'price',
    ];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
