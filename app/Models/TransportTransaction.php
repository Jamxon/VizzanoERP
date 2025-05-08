<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static create(array $array)
 * @method static findOrFail(mixed $id)
 */
class TransportTransaction extends Model
{
    use HasFactory;

    protected $table = 'transport_transactions';

    protected $fillable = [
        'transport_id',
        'date',
        'type',
        'amount',
    ];

    public function transport(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Transport::class);
    }
}
