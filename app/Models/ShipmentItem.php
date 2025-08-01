<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @method static whereHas(string $string, \Closure $param)
 */
class ShipmentItem extends Model
{
    protected $fillable = ['shipment_plan_id', 'model_id', 'quantity', 'completed', 'comment'];

    public function plan(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ShipmentPlan::class, 'shipment_plan_id');
    }

    public function model(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Model::class);
    }

    public function details(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ShipmentItemDetail::class);
    }
}
