<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static firstOrCreate(array $array)
 * @method static where(string $string, mixed $item_id)
 * @method static findOrFail(array $array)
 * @method static whereHas(string $string, \Closure $param)
 */
class StockBalance extends Model
{
    use HasFactory;

    protected $table = 'stock_balances';

    protected $fillable = [
        'item_id',
        'warehouse_id',
        'quantity',
        'order_id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'item_id',
        'warehouse_id',
        'order_id',
    ];

    public function item(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function warehouse(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function order(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
