<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static whereIn(string $string, array $submodelIds)
 * @method static find($categoryId)
 * @method static create(array $array)
 * @method static where(string $string, $submodelId)
 * @method static findOrFail($id)
 */
class SpecificationCategory extends Model
{
    use HasFactory;

    protected $table = 'specification_categories';

    protected $fillable = [
        'id',
        'name',
        'submodel_id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'submodel_id',
    ];

    public function submodel(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(OrderSubModel::class, 'submodel_id');
    }

    public function specifications(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PartSpecification::class, 'specification_category_id');
    }

    public function orderCuts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrderCut::class, 'specification_category_id');
    }
}
