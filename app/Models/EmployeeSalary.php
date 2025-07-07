<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeSalary extends Model
{
    protected $table = 'employee_salaries';

    protected $fillable = [
        'employee_id',
        'salary',
        'month',
        'year',
        'status',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function employee(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}