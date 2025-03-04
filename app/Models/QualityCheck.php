<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static create(array $array)
 * @method static where(string $string, int|string|null $id)
 */
class QualityCheck extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'order_sub_model_id',
        'status',
        'image',
        'comment',
    ];

    protected $hidden = [
        'user_id',
        'order_sub_model_id',
        'created_at',
        'updated_at',
    ];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order_sub_model(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(OrderSubModel::class);
    }

    public function qualityCheckDescriptions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(QualityCheckDescription::class);
    }

}
