<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static create(array $array)
 */
class Razryad extends Model
{
    use HasFactory;


    protected $fillable = ['name', 'salary'];

    protected $hidden = ['created_at', 'updated_at'];

    public function liningApplications()
    {
        return $this->hasMany(LiningApplication::class, 'razryad_id');
    }
}
