<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static where(string $string, $submodel_id)
 */
class SubModel extends Model
{
    use HasFactory;

    protected $table = "sub_models";

    protected $fillable = ['id','name','model_id'];

    protected $hidden = ['created_at', 'updated_at', 'model_id'];

    public function model(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Models::class, 'model_id');
    }

    public function specificationCategories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SpecificationCategory::class, 'submodel_id');
    }

    public function tarification_categories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TarificationCategory::class, 'submodel_id');
    }

    public function submodelSpend(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SubmodelSpend::class, 'submodel_id');
    }

    public function orderRecipes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrderRecipes::class, 'submodel_id');
    }
}