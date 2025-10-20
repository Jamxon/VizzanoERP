<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalaryChange extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'changed_by',
        'old_salary',
        'new_salary',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
