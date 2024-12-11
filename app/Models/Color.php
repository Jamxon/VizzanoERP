<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Color extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    protected $hidden = ['created_at', 'updated_at'];


    public function recipes()
    {
        return $this->hasMany(Recipe::class, 'color_id');
    }

    public function modelColors()
    {
        return $this->hasMany(ModelColor::class, 'color_id');
    }

    public function items()
    {
        return $this->hasMany(Item::class, 'color_id');
    }
}
