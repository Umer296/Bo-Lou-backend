<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'name', 'address', 'city', 'phone_number', 'email', 'payment_method',
    ];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
