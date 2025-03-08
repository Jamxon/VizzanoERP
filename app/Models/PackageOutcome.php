<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PackageOutcome extends Model
{
    use HasFactory;

    protected $table = 'package_outcomes';

    protected $fillable = [
        'order_id',
        'package_size',
        'package_quantity'
    ];

    protected $hidden = [
//        'created_at',
        'updated_at',
        'order_id'
    ];

    public function order(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
