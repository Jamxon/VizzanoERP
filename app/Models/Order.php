<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'quantity', 'status','start_date','end_date'];

    // Order modeli
    public function orderModels()
    {
        return $this->belongsToMany(Models::class, 'order_models', 'order_id', 'model_id')
            ->withPivot('quantity'); // Pivot jadvaldagi qo'shimcha ustunlarni olish
    }

}
