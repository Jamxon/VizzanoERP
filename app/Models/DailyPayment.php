<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'model_id',
        'order_id',
        'department_id',
        'payment_date',
        'quantity_produced',
        'calculated_amount',
        'employee_percentage',
        'updated_at',
    ];

    protected $table = 'daily_payments';

    // ✅ Relationships
    public function employee(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function model(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Models::class);
    }

    public function order(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function department(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
