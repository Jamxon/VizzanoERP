<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @method static create(array $array)
 * @method static where(string $string, $id)
 */
class EmployeeHolidays extends Model
{
    protected $table = 'employee_holidays';

    protected $fillable = [
        'employee_id',
        'start_date',
        'end_date',
        'comment',
    ];

    public function employee(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}