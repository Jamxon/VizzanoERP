<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @method static create(array $validated)
 * @method static updateOrCreate(array $array, array $array1)
 */
class SalaryPayment extends Model
{
    use HasFactory;

    protected $table = 'salary_payments';

    protected $fillable = [
        'employee_id',
        'amount',
        'type',      // 'advance' yoki 'salary'
        'month',     // '2025-05-01' formatda oyning 1-kuni
        'comment',
        'date',
    ];

    protected $casts = [
        'month' => 'date',
        'date' => 'date',
        'amount' => 'decimal:2',
    ];

    // Hodim bilan bog‘lanish
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
