<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonthlySelectedOrder extends Model
{
    protected $fillable = ['order_id', 'month'];

    protected $casts = [
        'month' => 'date',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
