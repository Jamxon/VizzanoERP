<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @method static create(array $array)
 * @method static where(string $string, mixed $tarificationId)
 * @method static updateOrCreate(array $array, array $array1)
 * @method static findOrFail($id)
 */
class EmployeeTarificationLog extends Model
{
    protected $fillable = [
        'employee_id',
        'tarification_id',
        'date',
        'quantity',
        'is_own',
        'amount_earned',
        'box_tarification_id',
    ];

    public function employee(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function tarification(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Tarification::class);
    }
}
