<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static where(string $string, $orderModelId)
 * @method static create(array $array)
 * @method static updateOrCreate(array $array, array $array1)
 * @method static whereHas(string $string, \Closure $param)
 * @method static findOrFail(mixed $orderSubModelId)
 */
class OrderSubModel extends Model
{
    use HasFactory;

    protected $table = "order_sub_models";

    protected $fillable = [
        'id',
        'order_model_id',
        'submodel_id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'order_model_id',
        'submodel_id',
    ];

    public function submodelSpend(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SubmodelSpend::class, 'submodel_id');
    }

    public function orderModel(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(OrderModel::class, 'order_model_id');
    }

    public function submodel(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SubModel::class);
    }

    public function group(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(OrderGroup::class, 'submodel_id');
    }

    public function orderRecipes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrderRecipes::class, 'submodel_id');
    }

    public function tarificationCategories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TarificationCategory::class, 'submodel_id');
    }

    public function specificationCategories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SpecificationCategory::class, 'submodel_id');
    }

    public function sewingOutputs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SewingOutputs::class, 'order_submodel_id');
    }

    public function qualityChecks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(QualityCheck::class);
    }

    public function otkOrderGroup(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(OtkOrderGroup::class, 'order_sub_model_id');
    }
}