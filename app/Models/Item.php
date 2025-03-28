<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static whereHas(string $string, \Closure $param)
 */
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
        'code',
        'branch_id'
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'unit_id',
        'color_id',
        'type_id',
        'branch_id'
    ];

    public function orders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrderModel::class, 'material_id', 'id');
    }

    public function unit(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function color(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Color::class);
    }

    public function recipes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Recipe::class);
    }

    public function type(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ItemType::class, 'type_id');
    }

    public function stok(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Stok::class, 'product_id');
    }
}
