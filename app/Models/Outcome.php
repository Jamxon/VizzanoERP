<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static find($id)
 */
class Outcome extends Model
{
    use HasFactory;

    protected $table = 'outcome';

    protected $fillable = [
        'number',
        'outcome_type',
        'warehouse_id',
        'status',
        'created_by_id',
        'created_at',
        'updated_at',
        'qr_code',
        'description',
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items()
    {
        return $this->hasMany(OutcomeItem::class);
    }

    public function productionOutcome()
    {
        return $this->hasOne(ProductionOutcome::class);
    }
}
