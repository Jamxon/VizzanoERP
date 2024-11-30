<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'quantity', 'status'];

    public function models()
    {
        return $this->hasMany(OrderModel::class);
    }
}
