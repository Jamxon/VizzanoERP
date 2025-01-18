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
        'type_id',
        'code'
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'unit_id',
        'color_id',
        'type_id'
    ];

    public function orders()
    {
        return $this->hasMany(OrderModel::class, 'material_id', 'id');
    }

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

    public function type()
    {
        return $this->belongsTo(ItemType::class, 'type_id');
    }

    public function stok()
    {
        return $this->hasOne(Stok::class, 'product_id');
    }
}
