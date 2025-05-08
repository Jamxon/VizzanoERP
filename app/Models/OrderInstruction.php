<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static updateOrCreate(array $array, array $array1)
 * @method static create(array $array)
 */
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
