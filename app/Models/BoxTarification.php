<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static create(array $array)
 */
class BoxTarification extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', 'submodel_id', 'tarification_id', 'size_id',
        'quantity', 'price', 'total', 'status'
    ];

    public function tarification(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Tarification::class);
    }

}
