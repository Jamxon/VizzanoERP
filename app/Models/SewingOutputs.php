<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\OrderSubModel;

/**
 * @method static create(mixed $item)
 * @method static where(string $string, string $string1, mixed $startDate)
 * @method static whereDate(string $string, string $string1, mixed $startDate)
 * @method static join(string $string, string $string1, string $string2, string $string3)
 * @method static whereIn(string $string, $orderSubmodelIds)
 */
class SewingOutputs extends Model
{
    use HasFactory;

    protected $table = 'sewing_outputs';

    protected $fillable = [
        'order_submodel_id',
        'quantity',
        'time_id',
        'comment',
        'created_at',
    ];

    public function orderSubmodel(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(OrderSubModel::class, 'order_submodel_id');
    }

    public function time(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Time::class,'time_id');
    }
}
