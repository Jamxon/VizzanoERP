<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static where(string $string, array|string|null $query)
 * @method static create(array $array)
 */
class OrderGroup extends Model
{
    use HasFactory;

    protected $table = 'order_groups';

    protected $fillable = [
        'order_id',
        'submodel_id',
        'group_id',
        'number',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'order_id',
        'submodel_id',
        'group_id',
    ];

    public function order(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderSubmodel(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(OrderSubModel::class, 'submodel_id');
    }

    public function group(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
