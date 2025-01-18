<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderSize extends Model
{
    use HasFactory;

    protected $table = "order_sizes";

    protected $fillable = [
        'order_model_id',
        'size_id',
        'quantity',
    ];

    protected $hidden = ['created_at', 'updated_at', 'order_model_id', 'size_id'];
}
