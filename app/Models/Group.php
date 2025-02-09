<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static whereIn(string $string, $pluck)
 * @method static create(array $all)
 * @method static find(mixed $id)
 */
class Group extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'department_id',
        'responsible_user_id',
        'total_work_time',
        'model_id'
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'total_work_time',
        'model_id',
        'responsible_user_id',
        'department_id'
    ];

    public function responsibleUser(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }
    public function department(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function employees(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Employee::class,'group_id');
    }

    public function orders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrderGroup::class,'group_id');
    }
}
