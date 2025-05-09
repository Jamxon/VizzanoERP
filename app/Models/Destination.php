<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property mixed $name
 * @method static firstOrCreate(array $array)
 * @method static where(string $string, mixed $branch_id)
 */
class Destination extends Model
{
    use HasFactory;

    protected $table = 'destinations';

    protected $fillable = [
        'name',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function stockEntries(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StockEntry::class);
    }
}
