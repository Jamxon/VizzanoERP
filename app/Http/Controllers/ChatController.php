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
    public function index()
    {
        $userId = Auth::id();

        // Foydalanuvchiga tegishli chatlar
        $chats = DB::table('chats')
            ->join('chat_users', 'chats.id', '=', 'chat_users.chat_id')
            ->where('chat_users.user_id', $userId)
            ->whereNull('chat_users.left_at')
            ->select('chats.*')
            ->orderByDesc('chats.updated_at')
            ->get();

        $result = [];

        foreach ($chats as $chat) {
            // Chat turi: group yoki personal
            $type = $chat->type;

            // Oxirgi xabar
            $lastMessage = DB::table('messages')
                ->where('chat_id', $chat->id)
                ->orderByDesc('created_at')
                ->first();

            // O‘qilmagan xabarlar soni
            $unreadCount = DB::table('messages')
                ->leftJoin('message_reads', function ($join) use ($userId) {
                    $join->on('messages.id', '=', 'message_reads.message_id')
                        ->where('message_reads.user_id', '=', $userId);
                })
                ->where('messages.chat_id', $chat->id)
                ->whereNull('message_reads.read_at')
                ->count();

            // Chat rasmi va nomi
            if ($type === 'group') {
                $chatName = $chat->name;
                $chatImage = $chat->image;
            } else {
                // Personal chat: boshqa foydalanuvchini topamiz
                $partner = DB::table('chat_users')
                    ->join('users', 'chat_users.user_id', '=', 'users.id')
                    ->where('chat_users.chat_id', $chat->id)
                    ->where('users.id', '!=', $userId)
                    ->select('users.id', 'users.name', 'users.image')
                    ->first();

                $chatName = $partner->name ?? 'No name';
                $chatImage = $partner->image ?? null;
            }

            // Vaqt formatlash
            $time = $lastMessage ? $lastMessage->created_at : $chat->updated_at;
            $formattedTime = \Carbon\Carbon::parse($time)->format('H:i');

            $result[] = [
                'id' => $chat->id,
                'name' => $chatName,
                'image' => $chatImage,
                'message' => $lastMessage->content ?? null,
                'newMessageCount' => $unreadCount,
                'time' => $formattedTime,
                'type' => $type,
            ];
        }

        return response()->json($result);
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
