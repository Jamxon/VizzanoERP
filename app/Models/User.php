<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [
            'role' => $this->role,  // Foydalanuvchining roli
        ];
    }
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = ['username', 'role_id', 'password'];

    public $with = ['role'];

    public $hidden = ['role_id', 'password'];
    // Role modeliga aloqani o'rnatamiz
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function employee()
    {
        return $this->hasOne(Employee::class, 'user_id');
    }

//    public function hasPermissionTo($permission)
//    {
//        return $this->role->permissions->contains('name', $permission);
//    }

    public function group()
    {
        return $this->hasOne(Group::class, 'responsible_user_id');
    }

    public function liningApplications()
    {
        return $this->hasMany(LiningApplication::class, 'user_id');
    }
}
