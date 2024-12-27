<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TypeWriter extends Model
{
    use HasFactory;

    protected $table = 'type_writers';

    protected $fillable = [
        'name',
        'comment',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function tarifications()
    {
        return $this->hasMany(Tarification::class);
    }
}
