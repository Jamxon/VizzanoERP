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
    public function store(Request $request, Chat $chat = null)
    {
        $request->validate([
            'type' => 'required|in:text,image,voice',
            'content' => 'nullable|string',
            'file' => 'nullable|file|max:5120',
            'reply_to' => 'nullable|exists:messages,id',
            'user_id' => 'nullable|exists:users,id', // personal chat uchun
        ]);

        $authId = Auth::id();

        // 1️⃣ Agar chat mavjud bo‘lmasa (personal chat yaratish kerak bo‘ladi)
        if (!$chat || !$chat->exists) {
            if (!$request->user_id) {
                return response()->json(['error' => 'user_id is required for personal chat'], 422);
            }

            $other = \App\Models\User::find($request->user_id);

            // branch cheklovi (faqat ceo istisno)
            $me = \App\Models\User::find($authId);
            if ($me->role->name !== 'ceo' && $me->employee->branch_id !== $other->employee->branch_id) {
                return response()->json(['error' => 'Not allowed'], 403);
            }

            // mavjud chatni qidiramiz
            $chat = \App\Models\Chat::where('type', 'personal')
                ->whereHas('users', fn($q) => $q->where('user_id', $authId))
                ->whereHas('users', fn($q) => $q->where('user_id', $other->id))
                ->first();

            // agar hali yo‘q bo‘lsa, yangisini yaratamiz
            if (!$chat) {
                \DB::transaction(function () use (&$chat, $me, $other) {
                    $chat = \App\Models\Chat::create([
                        'type' => 'personal',
                        'created_by' => $me->id,
                        'branch_id' => $me->employee->branch_id,
                    ]);

                    \App\Models\ChatUser::insert([
                        ['chat_id' => $chat->id, 'user_id' => $me->id],
                        ['chat_id' => $chat->id, 'user_id' => $other->id],
                    ]);
                });
            }
        }

        // 2️⃣ Xabarni tayyorlash
        $data = [
            'chat_id' => $chat->id,
            'sender_id' => $authId,
            'type' => $request->type,
            'content' => $request->content,
            'reply_to' => $request->reply_to,
        ];

        // 3️⃣ Faylni S3 ga saqlash
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $filename = time() . '_' . $authId . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs("chat_files/{$chat->id}", $filename, 's3');
            Storage::disk('s3')->setVisibility($path, 'public');
            $data['file_path'] = Storage::disk('s3')->url($path);
        }

        // 4️⃣ Message yaratish
        $message = \App\Models\Message::create($data);

        // 5️⃣ Oxirida javob
        return response()->json([
            'chat' => $chat->load(['users.user:id,username']),
            'message' => $message->load('sender:id,name'),
        ], 201);
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
