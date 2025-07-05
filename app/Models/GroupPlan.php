<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @method static updateOrCreate(array $array, array $array1)
 * @method static findOrFail($id)
 */
class GroupPlan extends Model
{
    protected $table = 'group_plans';

    protected $fillable = [
        'group_id',
        'month',
        'year',
        'quantity'
    ];

    public function group(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Group::class, 'group_id');
    }
}