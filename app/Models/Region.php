<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static firstOrCreate(array $array)
 */
class Region extends Model
{
    use HasFactory;

    protected $table = 'routes';

    protected $fillable = [
        'name',
    ];

}
