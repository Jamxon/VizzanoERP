<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static max(string $string)
 * @method static create(array $array)
 */
class SupplierOrder extends Model
{
    use HasFactory;

    protected $table = 'supplier_orders';

    protected $fillable = [
        'supplier_id',
        'code',
        'comment',
        'status',
        'created_by',
        'received_by',
        'deadline',
        'completed_date',
        'received_date',
    ];

    protected $hidden = [
        'updated_at',
        'deleted_at',
        'supplier_id',
        'created_by',
    ];

    public function supplier(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SupplierOrderItem::class);
    }
}
