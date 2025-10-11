<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ClearChatCacheListener
{
    /**
     * Yangi xabar yuborilib/qabul qilinganda cache'ni tozalash
     */
    public function handleMessageSent($event)
    {
        // Chat'dagi barcha userlar uchun cache'ni tozalash
        $chatUsers = DB::table('chat_users')
            ->where('chat_id', $event->message->chat_id)
            ->whereNull('left_at')
            ->pluck('user_id');

        foreach ($chatUsers as $userId) {
            Cache::forget("user_chats:{$userId}");
        }
    }

    /**
     * Xabar o'qilganda cache'ni tozalash
     */
    public function handleMessageRead($event)
    {
        Cache::forget("user_chats:{$event->userId}");
    }

    /**
     * Chat yangilanganda cache'ni tozalash
     */
    public function handleChatUpdated($event)
    {
        $chatUsers = DB::table('chat_users')
            ->where('chat_id', $event->chatId)
            ->whereNull('left_at')
            ->pluck('user_id');

        foreach ($chatUsers as $userId) {
            Cache::forget("user_chats:{$userId}");
        }
    }
}
