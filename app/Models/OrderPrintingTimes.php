<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static create(array $array)
 */
class OrderPrintingTimes extends Model
{
    use HasFactory;

    protected $table = 'order_printing_times';

    protected $fillable = [
        'id',
        'order_id',
        'planned_time',
        'actual_time',
        'status',
        'comment',
        'user_id'
    ];

    protected $hidden = [
        'order_id',
        'user_id',
        'updated_at',
    ];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
