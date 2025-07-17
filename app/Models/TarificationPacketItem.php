<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TarificationPacketItem extends Model
{
    protected $table = 'tarification_packet_items';

    protected $fillable = [
        'tarification_packet_id',
        'tarification_id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function tarificationPacket(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(TarificationPacket::class, 'tarification_packet_id');
    }

    public function tarification(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Tarification::class, 'tarification_id');
    }
}