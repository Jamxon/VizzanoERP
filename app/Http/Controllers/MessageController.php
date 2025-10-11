<?php

namespace App\Http\Controllers;

use App\Models\{Chat, Message, MessageRead};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class MessageController extends Controller
{
    /**
     * GET /chats/{chat}/messages
     */
    public function index(Chat $chat)
    {
        $this->authorizeChat($chat);

        $messages = $chat->messages()
            ->with(['sender:id,name', 'reply:id,content'])
            ->orderByDesc('id')
            ->paginate(30);

        return response()->json($messages);
    }

    /**
     * POST /chats/{chat}/messages
     */
    public function store(Chat $chat, Request $request)
    {
        $this->authorizeChat($chat);

        $request->validate([
            'type' => 'required|in:text,image,voice',
            'content' => 'nullable|string',
            'file' => 'nullable|file|max:5120',
            'reply_to' => 'nullable|exists:messages,id',
        ]);

        $data = [
            'chat_id' => $chat->id,
            'sender_id' => Auth::id(),
            'type' => $request->type,
            'content' => $request->content,
            'reply_to' => $request->reply_to,
        ];

        if ($request->hasFile('file')) {
            $path = $request->file('file')->store("chat_files/{$chat->id}", 'public');
            $data['file_path'] = $path;
        }

        $message = Message::create($data);

        return response()->json($message->load('sender:id,name'), 201);
    }

    /**
     * POST /messages/{message}/read
     */
    public function markAsRead(Message $message)
    {
        MessageRead::updateOrCreate(
            ['message_id' => $message->id, 'user_id' => Auth::id()],
            ['read_at' => now()]
        );

        return response()->json(['message' => 'Marked as read']);
    }

    /**
     * POST /messages/{message}/reply
     */
    public function reply(Message $message, Request $request)
    {
        $chat = $message->chat;
        $this->authorizeChat($chat);

        $request->validate(['content' => 'required|string']);

        $reply = Message::create([
            'chat_id' => $chat->id,
            'sender_id' => Auth::id(),
            'type' => 'text',
            'content' => $request->content,
            'reply_to' => $message->id,
        ]);

        return response()->json($reply, 201);
    }

    private function authorizeChat(Chat $chat)
    {
        if (!$chat->users()->where('user_id', Auth::id())->exists()) {
            abort(403, 'Access denied');
        }
    }
}
