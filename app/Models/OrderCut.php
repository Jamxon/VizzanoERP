<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static create(array $array)
 * @method static where(string $string, int|string|null $id)
 */
class OrderCut extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'order_id',
        'specification_category_id',
        'user_id',
        'cut_at',
        'quantity',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'order_id',
        'specification_category_id',
        'user_id',
    ];

    public function order(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function specificationCategory(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SpecificationCategory::class, 'specification_category_id');
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
