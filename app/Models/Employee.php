<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static whereIn(string $string, $groupIds)
 * @method static find($id)
 * @method static findOrFail($id)
 * @method static where(string $string, mixed $departmentId)
 * @property mixed $payment_type
 */
class Employee extends Model
{
    use HasFactory;

    protected $table = 'employees';

    protected $fillable = [
        'name',
        'phone',
        'group_id',
        'user_id',
        'payment_type',
        'salary',
        'hiring_date',
        'status',
        'address',
        'passport_number',
        'branch_id',
        'type',
        'salary',
        'birthday',
        'img',
        'position_id',
        'department_id',
        'comment',
        'gender',
        'kicked_date',
        'balance',
        'bonus'
    ];

    public function getImgAttribute($value): \Illuminate\Foundation\Application|string|\Illuminate\Contracts\Routing\UrlGenerator|\Illuminate\Contracts\Foundation\Application|null
    {
        if (empty($value)) {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        return url('storage/' . $value);
    }

    public function department(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function group(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Group::class, 'group_id');
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function attendances(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function tarifications(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Tarification::class, 'user_id');
    }

    public function branch(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function position(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function stockEntries(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StockEntry::class, 'user_id');
    }

    public function attendanceSalaries(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AttendanceSalary::class, 'employee_id');
    }

    public function employeeTarificationLogs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EmployeeTarificationLog::class, 'employee_id');
    }

    public function salaryPayments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SalaryPayment::class);
    }

    public function employeeResults(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EmployeeResult::class);
    }

    public function filteredTarifications($allowedIds)
    {
        return $this->tarifications()->whereIn('tarifications.id', $allowedIds);
    }

}
