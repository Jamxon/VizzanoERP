<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeTransportDaily extends Model
{
    protected $table = 'employee_transport_daily';

    protected $fillable = [
        'employee_id',
        'transport_id',
        'date',
        'note',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function transport()
    {
        return $this->belongsTo(Transport::class);
    }
}
