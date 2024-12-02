<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Models extends Model
{
    use HasFactory;

    protected $table = "models";

    protected $fillable = ['name', 'color'];

    protected $hidden = ['created_at', 'updated_at'];

    protected $with = ['detals', 'technicnormas', 'modelKarta',];

    public function modelKarta()
    {
        return $this->hasOne(ModelKarta::class, 'model_id');
    }
    public function detals()
    {
        return $this->hasMany(Detal::class, 'model_id');
    }
    public function groups()
    {
        return $this->hasOne(Group::class);
    }

    public function technicnormas()
    {
        return $this->hasOne(TechnicNorma::class, 'model_id');
    }

    public function orderModel()
    {
        return $this->hasMany(OrderModel::class);
    }
}
