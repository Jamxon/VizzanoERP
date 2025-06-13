<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @method static where(string $string, $id)
 * @method static create(array $validatedData)
 */
class ExampleOutputs extends Model
{
    protected $table = 'example_outputs';

    protected $fillable = [
        'order_submodel_id',
        'quantity',
        'time_id'
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'order_submodel_id',
        'time_id'
    ];

    public function orderSubmodel(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(OrderSubModel::class, 'order_submodel_id');
    }

    public function time(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Time::class, 'time_id');
    }
}