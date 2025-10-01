<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeMonthlySalary extends Model
{
    protected $fillable = [
        'employee_id',
        'month',
        'amount',
        'status',
        'created_by',
        'comment'
    ];

    protected $casts = [
        'month' => 'date:Y-m-d',
        'status' => 'boolean',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}