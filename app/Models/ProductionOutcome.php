<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionOutcome extends Model
{
    use HasFactory;

    protected $table = 'production_outcome';

    protected $fillable = [
        'outcome_id',
        'department_id',
        'group_id',
        'order_id',
        'received_by_id',
        'notes',
        'accepted_at',
        'acceptance_notes',
    ];

    public function outcome()
    {
        return $this->belongsTo(Outcome::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by_id');
    }
}
