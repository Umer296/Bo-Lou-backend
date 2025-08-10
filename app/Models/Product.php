<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'name', 'description', 'price', 'brand',
    ];

    public function orders()
    {
        return $this->belongsToMany(Order::class, 'order_items')
                    ->withPivot('product_quantity')
                    ->withTimestamps();
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }
}
