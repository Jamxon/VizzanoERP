<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property mixed $name
 */
class Destination extends Model
{
    use HasFactory;

    protected $table = 'destinations';

    protected $fillable = [
        'name',
    ];

    public function stockEntries(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StockEntry::class);
    }
}
