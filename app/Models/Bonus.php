<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @method static create(array $array)
 */
class Bonus extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'order_id',
        'type',
        'amount',
        'quantity',
        'old_balance',
        'new_balance',
        'created_by',
    ];

    public $timestamps = false; // Jadvalda created_at bor, lekin updated_at yo'q

    protected $casts = [
        'amount' => 'decimal:2',
        'old_balance' => 'decimal:2',
        'new_balance' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    // 💼 Hodim bilan bog‘liq munosabat
    public function employee(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    // 📦 Buyurtma bilan bog‘liq munosabat
    public function order(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    // 👤 Bonusni qo‘shgan foydalanuvchi
    public function creator(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}