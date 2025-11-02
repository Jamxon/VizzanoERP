<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DepartmentBudget extends Model
{
    protected $table = 'department_budgets';

    protected $fillable = [
        'department_id',
        'quantity',
        'type',
    ];

    // Bo'lim bilan bog‘lanish
    public function department()
    {
        return $this->belongsTo(Department::class);
    }
}