<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @method static create(array $data)
 */
class EmployeeResult extends Model
{
    protected $table = 'employee_results';

    protected $fillable = [
        'employee_id',
        'quantity',
        'time_id',
        'tarification_id',
        'created_by'
    ];

    protected $hidden = [
        'employee_id',
        'time_id',
        'tarification_id',
        'created_by'
    ];

    public function employee(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function time(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Time::class);
    }

    public function tarification(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Tarification::class);
    }

    public function createdBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}