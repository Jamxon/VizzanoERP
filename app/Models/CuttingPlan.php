<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @method static findOrFail($id)
 * @method static create(array $data)
 * @method static whereHas(string $string, \Closure $param)
 */
class CuttingPlan extends Model
{
    protected $table = 'cutting_plans';

    protected $fillable = [
        'department_id',
        'month',
        'year',
        'quantity'
    ];

    public function department(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}