<?php

namespace App\Models;

/**
 * @method belongsTo(string $class)
 * @method static with(string[] $array)
 */
class DailyPayment
{
    protected $table = 'daily_payments';

    protected $fillable = [
        'employee_id',
        'model_id',
        'order_id',
        'department_id',
        'payment_date',
        'quantity_produced',
        'calculated_amount',
        'employee_percentage',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function model()
    {
        return $this->belongsTo(Models::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

}