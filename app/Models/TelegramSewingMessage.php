<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @method static where(array $array)
 * @method static create(array $array)
 */
class TelegramSewingMessage extends Model
{
 protected $table = 'telegram_sewing_messages';

    protected $fillable = [
        'time_id',
        'date',
        'branch_id',
        'message_id',
        'chat_id',
    ];

    public function time(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Time::class, 'time_id');
    }
}