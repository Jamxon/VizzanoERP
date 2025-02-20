<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static where(string $string, mixed $id)
 * @method static create()
 * @method static updateOrCreate(array $array, array $array1)
 */
class OrderModel extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'model_id',
        'rasxod',
        'material_id'
    ];

    protected $hidden = ['created_at', 'updated_at', 'order_id', 'model_id','material_id'];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function sizes()
    {
        return $this->hasMany(OrderSize::class, 'order_model_id');
    }

    public function material()
    {
        return $this->belongsTo(Item::class, 'material_id', 'id');
    }

    public function model()
    {
        return $this->belongsTo(Models::class);
    }

    public function submodels(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrderSubModel::class, 'order_model_id');
    }

    public function outcomeItemModelDistributions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OutcomeItemModelDistrubition::class, 'model_id');
    }
}
