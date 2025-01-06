<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubModel extends Model
{
    use HasFactory;

    protected $table = "sub_models";

    protected $fillable = ['name','model_id'];

    protected $with = ['sizes', 'modelColors'];

    protected $hidden = ['created_at', 'updated_at', 'model_id'];

    public function model(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Models::class, 'model_id');
    }

    public function sizes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Size::class, 'submodel_id');
    }

    public function modelColors(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ModelColor::class, 'submodel_id');
    }

    public function specificationCategories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SpecificationCategory::class, 'submodel_id');
    }

    public function tarification_categories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TarificationCategory::class, 'submodel_id');
    }
}
