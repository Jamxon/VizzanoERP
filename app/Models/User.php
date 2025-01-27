<?php

namespace App\Models;

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
            'role' => $this->role,
        ];
    }

    protected $fillable = ['username', 'role_id', 'password'];

    public $with = ['role','employee','group'];

    public $hidden = ['role_id', 'password','created_at', 'updated_at'];

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function employee()
    {
        return $this->hasOne(Employee::class, 'user_id');
    }

    public function group()
    {
        return $this->hasOne(Group::class, 'responsible_user_id');
    }

    public function warehouses()
    {
        return $this->belongsToMany(Warehouse::class, 'warehouses_related_users', 'user_id', 'warehouse_id');
    }
}