<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderInstruction extends Model
{
    use HasFactory;

    protected $table = 'order_instructions';

    protected $fillable = [
        'order_id',
        'title',
        'description',
    ];

    protected $hidden = ['created_at', 'updated_at', 'order_id'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
