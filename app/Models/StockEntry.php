<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static create(array $array)
 * @method static where(string $string, string $string1)
 */

class StockEntry extends Model
{
    use HasFactory;

    protected $table = 'stock_entries';

    protected $fillable = [
        'warehouse_id',
        'type',
        'source_id',
        'destination_id',
        'comment',
        'order_id',
        'user_id',
        'contragent_id',
        'responsible_user_id',
    ];

    protected $hidden = [
        'updated_at',
        'warehouse_id',
        'source_id',
        'destination_id',
        'created_by',
        'order_id',
        'user_id',
        'contragent_id',
        'responsible_user_id',
    ];

    public function items(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StockEntryItem::class);
    }

    public function warehouse(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function source(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function destination(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Destination::class);
    }

    public function  employee(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Employee::class, 'user_id');
    }

    public function contragent(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Contragent::class);
    }

    public function order(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function responsibleUser(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }
}
