<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static where(string $string, $submodel_id)
 * @method static create(array $array)
 * @method static find(mixed $submodelId)
 * @method static updateOrCreate(array $array, array $array1)
 */
class SubModel extends Model
{
//    use HasFactory;

    protected $table = "sub_models";

    protected $fillable = ['name','model_id'];

    protected $hidden = ['created_at', 'updated_at', 'model_id'];

    public function model(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Models::class, 'model_id');
    }

    public function orderRecipes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrderRecipes::class, 'submodel_id');
    }
}