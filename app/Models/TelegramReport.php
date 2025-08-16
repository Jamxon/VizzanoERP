<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramReport extends Model
{
    protected $fillable = [
        'branch_id', 'date', 'chat_id', 'message_id', 'text'
    ];
}