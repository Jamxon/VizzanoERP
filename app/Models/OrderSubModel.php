<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderSubModel extends Model
{
    use HasFactory;

    protected $table = "order_sub_models";

    protected $fillable = [
        'order_model_id',
        'submodel_id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'order_model_id',
        'submodel_id',
    ];

    protected $with = ['submodel','orderRecipes'];

    public function orderModel(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(OrderModel::class, 'order_model_id');
    }

    public function submodel(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SubModel::class);
    }

    public function orderGroup(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(OrderGroup::class, 'submodel_id');
    }

    public function orderRecipes()
    {
        return $this->hasMany(OrderRecipes::class, 'submodel_id');
    }
}