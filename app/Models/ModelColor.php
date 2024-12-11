<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModelColor extends Model
{
    use HasFactory;

    protected $table = 'model_colors';

    protected $fillable = [
        'color_id',
        'submodel_id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'submodel_id',
        'color_id'
    ];

    protected $with = ['color'];

    public function color()
    {
        return $this->belongsTo(Color::class, 'color_id', 'id');
    }
    public function submodel()
    {
        return $this->belongsTo(Submodel::class, 'submodel_id');
    }

    public function recipes()
    {
        return $this->hasMany(Recipe::class, 'model_color_id');
    }
}
