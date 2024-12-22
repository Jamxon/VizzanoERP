<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderModel extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'model_id',
        'rasxod',
    ];

    protected $hidden = ['created_at', 'updated_at', 'order_id', 'model_id'];

    protected $with = ['model'];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function model()
    {
        return $this->belongsTo(Models::class);
    }
}
