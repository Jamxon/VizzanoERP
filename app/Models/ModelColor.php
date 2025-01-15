<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModelColor extends Model
{
    use HasFactory;

    protected $table = 'model_colors';

    protected $fillable = [
        'material_id',
        'submodel_id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'submodel_id',
        'material_id'
    ];

    public function material()
    {
        return $this->belongsTo(Item::class, 'material_id');
    }

    public function submodel()
    {
        return $this->belongsTo(SubModel::class, 'submodel_id');
    }

    public function recipes()
    {
        return $this->hasMany(Recipe::class, 'model_color_id');
    }
}
