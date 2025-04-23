<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static whereHas(string $string, \Closure $param)
 * @method static create(array $array)
 * @method static findOrFail(mixed $item_id)
 * @property mixed $currency_id
 * @property mixed $min_quantity
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
        'branch_id',
        'currency_id',
        'min_quantity',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'unit_id',
        'color_id',
        'type_id',
        'branch_id',
        'currency_id',
    ];

    public function getImageAttribute($value): \Illuminate\Foundation\Application|string|\Illuminate\Contracts\Routing\UrlGenerator|\Illuminate\Contracts\Foundation\Application|null
    {
        if (str_starts_with($value, 'items/')) {
            return url('storage/' . $value);
        }
        return null;
    }

    public function getImageRawAttribute(): ?string
    {
        return $this->attributes['image'] ?? null;
    }


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

    public function type(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ItemType::class, 'type_id');
    }

    public function stockBalances(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StockBalance::class);
    }

    public function stockEntryItem(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StockEntryItem::class);
    }

    public function currency(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

}
