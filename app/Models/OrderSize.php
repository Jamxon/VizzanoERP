<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static create(array $array)
 */
class OrderSize extends Model
{
    use HasFactory;

    protected $table = "order_sizes";

    protected $fillable = [
        'order_model_id',
        'size_id',
        'quantity',
    ];

    protected $hidden = ['created_at', 'updated_at', 'order_model_id', 'size_id'];

    public function orderModel()
    {
        return $this->belongsTo(OrderModel::class, 'order_model_id');
    }

    public function size()
    {
        return $this->belongsTo(Size::class, 'size_id');
    }
}
