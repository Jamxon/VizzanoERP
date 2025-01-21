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

    public function orderModel(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(OrderModel::class, 'order_id');
    }

    public function instructions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrderInstruction::class, 'order_id');
    }

    public function branch(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function contragent(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Contragent::class);
    }

    public function orderRecipes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrderRecipes::class, 'order_id');
    }

    public function orderPrintingTimes(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(OrderPrintingTimes::class, 'order_id');
    }
}
