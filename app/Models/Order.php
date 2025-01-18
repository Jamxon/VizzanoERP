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
        'branch_id',
        'comment',
        'contragent_id',
    ];

    protected $hidden = ['created_at', 'updated_at', 'branch_id', 'contragent_id'];

    public function orderModel()
    {
        return $this->hasOne(OrderModel::class, 'order_id');
    }

    public function instructions()
    {
        return $this->hasMany(OrderInstruction::class, 'order_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function contragent()
    {
        return $this->belongsTo(Contragent::class);
    }

    public function orderRecipes()
    {
        return $this->hasMany(OrderRecipes::class, 'order_id');
    }
}
