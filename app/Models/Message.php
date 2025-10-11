<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    protected $fillable = [
        'chat_id', 'sender_id', 'type', 'content', 'file_path', 'reply_to', 'from_id'
    ];

    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function reply(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'reply_to');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(MessageRead::class);
    }
}
