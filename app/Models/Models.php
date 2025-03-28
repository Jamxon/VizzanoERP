<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property mixed $name
 * @property mixed $rasxod
 * @property mixed $id
 * @property mixed $materials
 * @property mixed $sizes
 * @property mixed $submodels
 * @property mixed $images
 * @method static create(array $array)
 * @method static with(string[] $array)
 * @method static find(mixed $modelId)
 */
class Models extends Model
{
    use HasFactory;

    protected $table = "models";

    protected $fillable = ['name', 'rasxod','branch_id'];

    protected $hidden = ['created_at', 'updated_at'];

    public function materials(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Materials::class, 'model_id');
    }

    public function sizes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Size::class, 'model_id');
    }

    public function submodels(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SubModel::class, 'model_id');
    }

    public function images(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ModelImages::class, 'model_id');
    }
}
