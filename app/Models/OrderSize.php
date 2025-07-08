<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static create(array $array)
 * @method static updateOrCreate(array $array, array $array1)
 * @method static where(string $string, mixed $id)
 * @method static find(mixed $size_id)
 */
class OrderSize extends Model
{
    use HasFactory;

    protected $table = "order_sizes";

    protected $fillable = [
        'order_model_id',
        'size_id',
        'quantity',
        'color_id'
    ];

    protected $hidden = ['created_at', 'updated_at', 'order_model_id', 'size_id', 'color_id'];

    public function orderModel(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(OrderModel::class, 'order_model_id');
    }

    public function size(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Size::class, 'size_id');
    }

    public function color(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Color::class, 'color_id');
    }
}
