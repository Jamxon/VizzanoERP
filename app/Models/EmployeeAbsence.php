<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @method static create(array $array)
 */
class EmployeeAbsence extends Model
{
    protected $table = 'employee_absences';

    protected $fillable = [
        'employee_id',
        'start_date',
        'end_date',
        'comment',
        'image'
    ];

    protected  $hidden = [
        'created_at',
        'updated_at',
    ];

    public function employee(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}