<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @method static find(mixed $contragent_id)
 * @method static updateOrCreate(array $array, array $array1)
 * @method static create(array $array)
 */
class Contragent extends Model
{
    protected $table = "contragent";

    protected $fillable = [
        'name',
        'description',
        'is_market'
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'is_market'
    ];
}