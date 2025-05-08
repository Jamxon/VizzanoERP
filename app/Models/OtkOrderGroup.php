<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static create(array $array)
 * @method static whereIn(string $string, $groups)
 * @method static where(string $string, mixed $order_sub_model_id)
 */
class OtkOrderGroup extends Model
{
    use HasFactory;

    protected $table = 'otk_order_groups';

    protected $fillable = [
        'order_sub_model_id',
        'group_id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'order_sub_model_id',
        'group_id',
    ];

    public function group(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function orderSubModel(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(OrderSubModel::class);
    }
}
