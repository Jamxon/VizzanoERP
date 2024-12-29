<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'department_id',
        'responsible_user_id',
        'total_work_time',
        'model_id'];

    public function users()
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function models()
    {
        return $this->belongsTo(Models::class, 'model_id');
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }
}
