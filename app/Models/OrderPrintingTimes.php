<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderPrintingTimes extends Model
{
    use HasFactory;

    protected $table = 'order_printing_times';

    protected $fillable = [
        'order_model_id',
        'planned_time',
        'actual_time',
        'status',
        'comment',
        'user_id'
    ];

    protected $hidden = [
        'order_model_id',
        'user_id',
        'updated_at',
    ];

    protected $with = ['orderModel'];

    public function orderModel()
    {
        return $this->belongsTo(OrderModel::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
