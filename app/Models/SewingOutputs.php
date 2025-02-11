<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static create(mixed $item)
 * @method static where(string $string, string $string1, mixed $startDate)
 * @method static whereDate(string $string, string $string1, mixed $startDate)
 */
class SewingOutputs extends Model
{
    use HasFactory;

    protected $table = 'sewing_outputs';

    protected $fillable = [
        'order_submodel_id',
        'quantity',
        'time_id',
        'comment'
    ];

    public function orderSubmodel(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(OrderSubmodel::class,'order_submodel_id');
    }

    public function time(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Time::class,'time_id');
    }
}
