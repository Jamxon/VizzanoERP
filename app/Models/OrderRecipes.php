<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderRecipes extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'item_id',
        'model_color_id',
        'quantity',
        'size_id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'item_id',
        'size_id',
        'model_color_id',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function modelColor()
    {
        return $this->belongsTo(ModelColor::class, 'model_color_id');
    }

    public function size()
    {
        return $this->belongsTo(Size::class);
    }
}
