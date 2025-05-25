<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shipment extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'brand', 'product_quantity', 'product_description', 'arriving_time_date', 'price',
    ];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
