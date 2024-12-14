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

    public function model()
    {
        return $this->belongsTo(Models::class, 'model_id');
    }

    public function sizes()
    {
        return $this->hasMany(Size::class, 'submodel_id');
    }

    public function modelColors()
    {
        return $this->hasMany(ModelColor::class, 'submodel_id');
    }

    public function details()
    {
        return $this->hasMany(Detail::class, 'submodel_id');
    }
}
