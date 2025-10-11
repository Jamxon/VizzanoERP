<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatUser extends Model
{
    protected $fillable = [
        'chat_id', 'user_id', 'can_send_message', 'can_add_members', 'can_edit_permissions', 'joined_at', 'left_at'
    ];

    public $timestamps = false;

    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
