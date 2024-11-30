<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Models extends Model
{
    use HasFactory;

    protected $table = "models";

    protected $fillable = ['name', 'color'];

    public function groups()
    {
        return $this->hasOne(Group::class);
    }

    public function technicnormas()
    {
        return $this->hasOne(TechnicNorma::class);
    }

    public function orderModel()
    {
        return $this->hasMany(OrderModel::class);
    }
}
