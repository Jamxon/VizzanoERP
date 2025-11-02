<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $table = 'expenses';

    protected $fillable = [
        'name',
        'quantity',
        'type',
        'branch_id',
    ];
}