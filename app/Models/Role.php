<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Permission;

class Role extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description'];

    protected $hidden = ['created_at', 'updated_at'];

    // Foydalanuvchilarni olish
    public function users()
    {
        return $this->hasMany(User::class);
    }

    // Ruxsatlarni olish (agar kerak bo'lsa)
    public function permissions()
    {
        return $this->belongsToMany(Permission::class);
    }
}
