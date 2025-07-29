<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static create(array $array)
 * @method static where(string $string, int|string|null $id)
 * @method static find($id)
 * @method static whereMonth(string $string, $month)
 * @method static whereHas(string $string, \Closure $param)
 * @method static whereDate(string $string, $date)
 */
class OrderCut extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'order_id',
        'user_id',
        'cut_at',
        'quantity',
        'status',
        'submodel_id',
        'size_id'
    ];

    protected $hidden = [
        'order_id',
        'user_id',
        'submodel_id',
        'size_id',
    ];

    public function order(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function submodel(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(OrderSubModel::class, 'submodel_id');
    }

    public function size(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(OrderSize::class, 'size_id');
    }
}
