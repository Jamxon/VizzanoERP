<?php

namespace App\Http\Controllers;

use App\Models\{Chat, ChatUser, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ChatController extends Controller
{
    /**
     * GET /chats
     * Foydalanuvchining barcha chatlari
     */
    // public function index()
    // {
    //     $chats = Chat::query()
    //         ->whereHas('users', fn($q) => $q->where('user_id', Auth::id()))
    //         ->with(['users.user.employee', 'messages' => fn($q) => $q->latest()->limit(1)])
    //         ->get();

    //     return response()->json($chats);
    // }

    public function index()
    {
        $userId = Auth::id();

        $chats = \DB::table('chats')
            ->join('chat_users', 'chats.id', '=', 'chat_users.chat_id')
            ->where('chat_users.user_id', $userId)
            ->whereNull('chat_users.left_at')
            ->select([
                'chats.id',
                'chats.type',
                'chats.name',
                'chats.image',
                'chats.updated_at',
                // oxirgi xabar
                \DB::raw("(SELECT content FROM messages m WHERE m.chat_id = chats.id ORDER BY m.created_at DESC LIMIT 1) as last_message"),
                \DB::raw("(SELECT created_at FROM messages m WHERE m.chat_id = chats.id ORDER BY m.created_at DESC LIMIT 1) as last_message_time"),
                // o‘qilmagan xabarlar soni
                \DB::raw("(
                    SELECT COUNT(*) FROM messages m
                    LEFT JOIN message_reads r ON r.message_id = m.id AND r.user_id = $userId
                    WHERE m.chat_id = chats.id AND r.read_at IS NULL AND m.sender_id != $userId
                ) as unread_count"),
                // personal chatda boshqa userni olish
                \DB::raw("(
                    CASE
                        WHEN chats.type = 'personal' THEN (
                            SELECT u.name FROM chat_users cu
                            JOIN users u ON u.id = cu.user_id
                            WHERE cu.chat_id = chats.id AND u.id != $userId
                            LIMIT 1
                        )
                        ELSE chats.name
                    END
                ) as chat_name"),
                \DB::raw("(
                    CASE
                        WHEN chats.type = 'personal' THEN (
                            SELECT u.image FROM chat_users cu
                            JOIN users u ON u.id = cu.user_id
                            WHERE cu.chat_id = chats.id AND u.id != $userId
                            LIMIT 1
                        )
                        ELSE chats.image
                    END
                ) as chat_image"),
                // agar oxirgi xabar ushbu user tomonidan yuborilgan bo‘lsa lekin o‘qilmagan bo‘lsa -1
                \DB::raw("(
                    CASE
                        WHEN (
                            SELECT m.sender_id FROM messages m
                            WHERE m.chat_id = chats.id ORDER BY m.created_at DESC LIMIT 1
                        ) = $userId
                        AND (
                            SELECT COUNT(*) FROM message_reads r
                            JOIN messages m2 ON r.message_id = m2.id
                            WHERE m2.chat_id = chats.id AND m2.sender_id = $userId AND r.read_at IS NULL
                        ) > 0
                        THEN -1
                        ELSE 0
                    END
                ) as self_unread_flag")
            ])
            ->orderByDesc('chats.updated_at')
            ->get()
            ->map(function ($chat) {
                // oldingi unread_count va self_unread_flag birlashtiriladi
                $chat->newMessageCount = (int) $chat->self_unread_flag !== 0
                    ? -1
                    : (int) $chat->unread_count;

                // frontendga kerakli format
                return [
                    'name' => $chat->chat_name,
                    'image' => $chat->chat_image,
                    'message' => $chat->last_message,
                    'newMessageCount' => $chat->newMessageCount,
                    'time' => $chat->last_message_time
                        ? \Carbon\Carbon::parse($chat->last_message_time)->format('H:i')
                        : null,
                    'type' => $chat->type,
                ];
            });

        return response()->json($chats);
    }


    /**
     * POST /chats/personal
     * Personal chat yaratish (agar mavjud bo‘lmasa)
     */
    public function createPersonal(Request $request)
    {
        $request->validate(['user_id' => 'required|exists:users,id']);
        $me = Auth::user();
        $other = User::find($request->user_id);

        // branch cheklovi (ceo har kimga yozoladi)
        if ($me->role->name !== 'ceo' && $me->employee->branch_id !== $other->employee->branch_id) {
            return response()->json(['error' => 'Not allowed'], 403);
        }

        // mavjud personal chatni topish
        $chat = Chat::where('type', 'personal')
            ->whereHas('users', fn($q) => $q->where('user_id', $me->id))
            ->whereHas('users', fn($q) => $q->where('user_id', $other->id))
            ->first();

        if (!$chat) {
            DB::transaction(function () use (&$chat, $me, $other) {
                $chat = DB::table('chats')->insertGetId([
                    'type' => 'personal',
                    'created_by' => $me->id,
                    'branch_id' => $me->employee->branch_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                ChatUser::insert([
                    ['chat_id' => $chat->id, 'user_id' => $me->id],
                    ['chat_id' => $chat->id, 'user_id' => $other->id],
                ]);
            });
        }

        return response()->json($chat->load('users.user:id,username'));
    }

    /**
     * POST /chats/group
     * Faqat CEO group yarata oladi
     */
    public function createGroup(Request $request)
    {
        $user = Auth::user();
        if ($user->role->name !== 'ceo') {
            return response()->json(['error' => 'Only CEO can create groups'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'image' => 'nullable|image|max:20480',
        ]);

        if ($request->hasFile('image')) {
                $file = $request->file('image');
                $filename = time() . '.' . $file->getClientOriginalExtension();

                $path = $file->storeAs('groupImages', $filename, 's3');

                Storage::disk('s3')->setVisibility($path, 'public');

                $image = Storage::disk('s3')->url($path);
        }

        $chat = DB::table('chats')->insertGetId([
            'type' => 'group',
            'name' => $request->name,
            'image' => $image ?? null,
            'created_by' => $user->id,
            'branch_id' => $user->branch_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('chat_users')->insert([
            'chat_id' => $chat->id,
            'user_id' => $user->id,
            'can_send_message' => true,
            'can_add_members' => true,
            'can_edit_permissions' => true,
            'joined_at' => now(),
        ]);

        return response()->json($chat, 201);
    }

    /**
     * POST /chats/{chat}/users
     * Groupga foydalanuvchi qo‘shish
     */
    public function addUser(Chat $chat, Request $request)
    {
        $me = Auth::user();
        if ($chat->type !== 'group') return response()->json(['error' => 'Not a group'], 400);

        $request->validate(['user_id' => 'required|exists:users,id']);

        $perm = ChatUser::where('chat_id', $chat->id)->where('user_id', $me->id)->first();
        if (!$perm || !$perm->can_add_members) {
            return response()->json(['error' => 'No permission'], 403);
        }

        DB::table('chat_users')->updateOrInsert(
            ['chat_id' => $chat->id, 'user_id' => $request->user_id],
            ['joined_at' => now(), 'left_at' => null]
        );

        // 2️⃣ Guruhda tizim xabari yuborish
        $addedUser = User::find($request->user_id);
        $sender = Auth::user();
        Message::create([
            'chat_id'   => $chat->id,
            'sender_id' => $sender->id,
            'type'      => 'system',
            'content'   => "{$sender->employee->name} {$addedUser->employee->name} ni guruhga qo‘shdi.",
            'created_at'=> now(),
        ]);

        return response()->json(['message' => 'User added']);
    }

    public function removeUser(Chat $chat, Request $request)
    {
        $me = Auth::user();
        if ($chat->type !== 'group') return response()->json(['error' => 'Not a group'], 400);

        $request->validate(['user_id' => 'required|exists:users,id']);

        $perm = ChatUser::where('chat_id', $chat->id)->where('user_id', $me->id)->first();
        if (!$perm || !$perm->can_add_members) {
            return response()->json(['error' => 'No permission'], 403);
        }

        DB::table('chat_users')
        ->where('chat_id', $chat->id)
        ->where('user_id', $request->user_id)
        ->delete();

        // 2️⃣ Guruhda tizim xabari yuborish
        $removedUser = User::find($request->user_id);
        $sender = Auth::user();

        Message::create([
            'chat_id'   => $chat->id,
            'sender_id' => $sender->id,
            'type'      => 'system',
            'content'   => "{$sender->employee->name} {$removedUser->employee->name} ni guruhdan olib tashladi.",
            'created_at'=> now(),
        ]);
        
        return response()->json(['message' => 'User removed']);    
    }

    /**
     * PATCH /chats/{chat}/permissions/{user}
     * Group ichida ruxsatni o‘zgartirish
     */
    public function updatePermission(Chat $chat, User $user, Request $request)
    {
        $me = Auth::user();
        $perm = ChatUser::where('chat_id', $chat->id)->where('user_id', $me->id)->first();

        if (!$perm || !$perm->can_edit_permissions) {
            return response()->json(['error' => 'No permission'], 403);
        }

        $data = $request->only(['can_send_message', 'can_add_members', 'can_edit_permissions']);
        DB::table('chat_users')
            ->where('chat_id', $chat->id)
            ->where('user_id', $user->id)
            ->update($data);

        return response()->json(['message' => 'Permissions updated']);
    }
}
