<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Application extends Model
{
    use HasFactory;

    protected $table = 'applications';

    protected $fillable = [
        'name',
    ];

    protected $hidden = ['created_at', 'updated_at'];


    protected $with = ['liningPreparations'];

    public function liningPreparations()
    {
        return $this->hasMany(LiningPreparation::class, 'application_id');
    }
}
