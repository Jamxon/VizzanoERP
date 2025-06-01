<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

class AttendanceSalary extends Authenticatable
{
    use SoftDeletes;

    protected $table = "attendance_salary";

    protected $fillable = [
        'employee_id',
        'attendance_id',
        'amount',
        'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function attendance()
    {
        return $this->belongsTo(Attendance::class);
    }
}