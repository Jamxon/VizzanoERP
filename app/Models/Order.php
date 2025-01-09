<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $table = 'orders';
    
    protected $fillable = [
        'name',
        'quantity',
        'status',
        'start_date',
        'end_date',
        'rasxod',
        'branch_id'
    ];

    protected $hidden = ['created_at', 'updated_at', 'branch_id'];

    protected $with = ['orderModels'];

    public function orderModels()
    {
        return $this->hasMany(OrderModel::class, 'order_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
