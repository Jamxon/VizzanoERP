<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderSubModel extends Model
{
    use HasFactory;

    protected $table = "order_sub_models";

    protected $fillable = [
        'order_model_id',
        'submodel_id',
        'size_id',
        'quantity',
        'model_color_id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function orderModel()
    {
        return $this->belongsTo(OrderModel::class);
    }

    public function submodel()
    {
        return $this->belongsTo(SubModel::class);
    }

    public function size()
    {
        return $this->belongsTo(Size::class);
    }
}
