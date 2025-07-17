<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @method static where(string $string, $employeeId)
 */
class TarificationPacket extends Model
{
    protected $table = 'tarification_packets';

    protected $fillable = [
        'employee_id',
        'date'
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function employee(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function tarificationPacketsItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TarificationPacketItem::class, 'tarification_packet_id');
    }
}