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
        'quantity',
        'submodel_id'
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'item_id',
        'submodel_id',
        'order_id'
    ];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function submodel()
    {
        return $this->belongsTo(SubModel::class);
    }
}