<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static create(array $array)
 * @method static insert(array $planItems)
 * @method static updateOrInsert(array $array, array $array1)
 * @method static updateOrCreate(array $array, array $array1)
 */
class DailyPlanItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'daily_plan_id',
        'tarification_id',
        'count',
        'total_minutes',
        'amount_earned',
        'actual'
    ];

    public function dailyPlan(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(DailyPlan::class);
    }

    public function tarification(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Tarification::class);
    }
}
