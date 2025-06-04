<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static firstOrCreate(array $array, string[] $array1)
 */
class Cashbox extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'branch_id',
    ];

    public function balances(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CashboxBalance::class);
    }

    public function transactions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CashboxTransaction::class);
    }
}
