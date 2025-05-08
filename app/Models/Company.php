<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'img', 'description'];

    // Filiallarga aloqani o'rnatamiz
    public function branches()
    {
        return $this->hasMany(Branch::class);
    }
}
