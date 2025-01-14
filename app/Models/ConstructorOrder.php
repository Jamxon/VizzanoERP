<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConstructorOrder extends Model
{
    use HasFactory;

    protected $table = 'constructor_orders';

    protected $fillable = [
        'order_model_id',
        'submodel_id',
        'size_id',
        'quantity',
        'status',
        'comment'
    ];

    protected $hidden = [
        'order_model_id',
        'submodel_id',
        'size_id',
        'updated_at',
    ];

    public function orderModel()
    {
        return $this->belongsTo(OrderModel::class);
    }

    public function submodel()
    {
        return $this->belongsTo(OrderSubModel::class);
    }
}
