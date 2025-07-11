<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static where(string $string, $id)
 * @method static findOrFail($id)
 * @method static create(array $array)
 */
class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'responsible_user_id',
        'main_department_id',
        'start_time',
        'end_time',
        'break_time',
    ];

    protected $hidden = ['created_at', 'updated_at','branch_id'];

    public function groups(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Group::class);
    }

    public function responsibleUser(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function setStartTimeAttribute($value): void
    {
        $this->attributes['start_time'] = $value ? date("H:i", strtotime($value)) : null;
    }

    public function setEndTimeAttribute($value): void
    {
        $this->attributes['end_time'] = $value ? date("H:i", strtotime($value)) : null;
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function mainDepartment(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(MainDepartment::class, 'main_department_id');
    }

    public function positions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Position::class, 'department_id');
    }

    public function employees(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Employee::class, 'department_id');
    }
}
