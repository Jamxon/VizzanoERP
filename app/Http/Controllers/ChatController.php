<?php

namespace App\Http\Controllers;

use App\Models\{Chat, ChatUser, User, Message};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;

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
        $cacheKey = "user_chats:{$userId}";
        
        // Cache'dan olish (30 soniya)
        $chats = Cache::remember($cacheKey, 30, function () use ($userId) {
            return \DB::table('chats')
                ->join('chat_users', 'chats.id', '=', 'chat_users.chat_id')
                
                // Oxirgi xabarni JOIN orqali olish
                ->leftJoin(\DB::raw('(
                    SELECT 
                        chat_id,
                        content as last_message,
                        created_at as last_message_time,
                        sender_id as last_sender_id
                    FROM messages m1
                    WHERE created_at = (
                        SELECT MAX(created_at) 
                        FROM messages m2 
                        WHERE m2.chat_id = m1.chat_id
                    )
                ) as last_messages'), 'chats.id', '=', 'last_messages.chat_id')
                
                // O'qilmagan xabarlar sonini olish
                ->leftJoin(\DB::raw("(
                    SELECT 
                        m.chat_id,
                        COUNT(*) as unread_count
                    FROM messages m
                    LEFT JOIN message_reads r ON r.message_id = m.id AND r.user_id = {$userId}
                    WHERE r.read_at IS NULL AND m.sender_id != {$userId}
                    GROUP BY m.chat_id
                ) as unread_messages"), 'chats.id', '=', 'unread_messages.chat_id')
                
                // O'zi yuborgan o'qilmagan xabarlar sonini olish
                ->leftJoin(\DB::raw("(
                    SELECT 
                        m.chat_id,
                        COUNT(*) as self_unread_count
                    FROM messages m
                    JOIN message_reads r ON r.message_id = m.id
                    WHERE m.sender_id = {$userId} AND r.read_at IS NULL
                    GROUP BY m.chat_id
                ) as self_unread_messages"), 'chats.id', '=', 'self_unread_messages.chat_id')
                
                // Personal chatdagi boshqa user ma'lumotlarini olish
                ->leftJoin(\DB::raw("(
                    SELECT 
                        cu.chat_id,
                        e.name as other_user_name,
                        e.img as other_user_image
                    FROM chat_users cu
                    JOIN users u ON u.id = cu.user_id
                    JOIN employees e ON e.user_id = u.id
                    WHERE u.id != {$userId}
                ) as other_users"), 'chats.id', '=', 'other_users.chat_id')
                
                ->where('chat_users.user_id', $userId)
                ->whereNull('chat_users.left_at')
                
                ->select([
                    'chats.id',
                    'chats.type',
                    'chats.name',
                    'chats.image',
                    'chats.updated_at',
                    'last_messages.last_message',
                    'last_messages.last_message_time',
                    'last_messages.last_sender_id',
                    \DB::raw('COALESCE(unread_messages.unread_count, 0) as unread_count'),
                    \DB::raw('COALESCE(self_unread_messages.self_unread_count, 0) as self_unread_count'),
                    'other_users.other_user_name',
                    'other_users.other_user_image'
                ])
                ->orderByDesc('chats.updated_at')
                ->limit(50) // Faqat oxirgi 50 ta chat
                ->get()
                ->map(function ($chat) use ($userId) {
                    // newMessageCount hisoblash
                    $newMessageCount = 0;
                    if ($chat->last_sender_id == $userId && $chat->self_unread_count > 0) {
                        $newMessageCount = -1;
                    } else {
                        $newMessageCount = (int)$chat->unread_count;
                    }
                    
                    return [
                        'name' => $chat->type === 'personal' ? $chat->other_user_name : $chat->name,
                        'image' => $chat->type === 'personal' ? $chat->other_user_image : $chat->image,
                        'message' => $chat->last_message,
                        'newMessageCount' => $newMessageCount,
                        'time' => $chat->last_message_time
                            ? \Carbon\Carbon::parse($chat->last_message_time)->format('H:i')
                            : null,
                        'type' => $chat->type,
                    ];
                });
        });

        return response()->json($chats);
    }

    // Cache'ni tozalash uchun helper method
    public function clearChatCache($userId)
    {
        Cache::forget("user_chats:{$userId}");
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
                // Chat yaratamiz model orqali
                $chat = Chat::create([
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

        // agar transaction ichida yaratgan bo‘lsak, u global scope’da qayta topiladi
        if (is_int($chat)) {
            $chat = Chat::find($chat);
        }

        return response()->json(
            $chat->load(['users.user:id,username'])
        );
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
