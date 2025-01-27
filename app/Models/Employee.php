<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static whereIn(string $string, $groupIds)
 */
class Employee extends Model
{
    use HasFactory;


    protected $fillable = [
        'name', 'phone', 'group_id', 'user_id', 'payment_type',
        'salary', 'hiring_date', 'status', 'address', 'passport_number'
        ,'branch_id'
    ];
    public function group()
    {
        return $this->belongsTo(Group::class, 'group_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function tarifications()
    {
        return $this->hasMany(Tarification::class, 'user_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
