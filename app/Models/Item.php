<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'price',
        'unit_id',
        'color_id',
        'image',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function color()
    {
        return $this->belongsTo(Color::class);
    }

    public function recipes()
    {
        return $this->hasMany(Recipe::class);
    }
}
