<?php

namespace App\Http\Controllers;

use App\Models\{Chat, ChatUser, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    /**
     * GET /chats
     * Foydalanuvchining barcha chatlari
     */
    public function index()
    {
        $chats = Chat::query()
            ->whereHas('users', fn($q) => $q->where('user_id', Auth::id()))
            ->with(['users.user:id,username', 'messages' => fn($q) => $q->latest()->limit(1)])
            ->get();

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
                $chat = Chat::create([
                    'type' => 'personal',
                    'created_by' => $me->id,
                    'branch_id' => $me->branch_id
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
        if ($user->role !== 'ceo') {
            return response()->json(['error' => 'Only CEO can create groups'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'image' => 'nullable|string'
        ]);

        $chat = Chat::create([
            'type' => 'group',
            'name' => $request->name,
            'image' => $request->image,
            'created_by' => $user->id
        ]);

        ChatUser::create([
            'chat_id' => $chat->id,
            'user_id' => $user->id,
            'can_add_members' => true,
            'can_edit_permissions' => true
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

        ChatUser::updateOrCreate([
            'chat_id' => $chat->id,
            'user_id' => $request->user_id,
        ], [
            'joined_at' => now(),
            'left_at' => null
        ]);

        return response()->json(['message' => 'User added']);
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
        ChatUser::where('chat_id', $chat->id)->where('user_id', $user->id)->update($data);

        return response()->json(['message' => 'Permissions updated']);
    }
}
