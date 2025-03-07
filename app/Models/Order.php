<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property mixed $orderRecipes
 * @property mixed $id
 * @property mixed $orderModel
 * @property mixed $contragent_id
 * @property mixed $comment
 * @property mixed $branch_id
 * @property mixed $end_date
 * @property mixed $start_date
 * @property mixed $status
 * @property mixed $quantity
 * @property mixed $name
 * @property mixed $rasxod
 * @property mixed $orderPrintingTime
 * @property mixed $instructions
 * @property mixed $branch
 * @property mixed $contragent
 * @property mixed $created_at
 * @property mixed $updated_at
 * @property mixed $price
 * @property mixed $recipes
 *
 * @method static find(mixed $order_id)
 * @method static where(string $string, mixed $status)
 * @method static create(array $array)
 * @method orderSizes()
 * @method static orderBy(string $string, string $string1)
 */
class Order extends Model
{
    use HasFactory;

    protected $table = 'orders';

    protected $fillable = [
        'id',
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

    protected $casts = [
        'price' => 'float',
    ];

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

    public function orderPrintingTime(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(OrderPrintingTimes::class, 'order_id');
    }

    public function orderCuts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrderCut::class, 'order_id');
    }

    public function orderGroups(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrderGroup::class, 'order_id');
    }
}
