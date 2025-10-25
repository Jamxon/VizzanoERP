<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GroupChange extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'changed_by',
        'old_group_id',
        'new_group_id',
        'ip',
        'device'
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function oldGroup()
    {
        return $this->belongsTo(Group::class, 'old_group_id');
    }

    public function newGroup()
    {
        return $this->belongsTo(Group::class, 'new_group_id');
    }
}
