<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MonthlyClosure extends Model
{
    use HasFactory;

    protected $table = 'monthly_closures';

    protected $fillable = ['year', 'month', 'is_closed'];

    public static function isMonthClosed($year, $month)
    {
        return self::where('year', $year)->where('month', $month)->where('is_closed', true)->exists();
    }
}
