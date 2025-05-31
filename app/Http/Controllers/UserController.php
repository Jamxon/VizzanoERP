<?php

namespace App\Http\Controllers;

use App\Http\Resources\GetUserResource;
use App\Models\Employee;
use App\Models\Log;
use App\Models\User;
use App\Models\Issue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;


class UserController extends Controller
{
    public function getProfile(): \Illuminate\Http\JsonResponse
    {
        $user = Auth::user();

        $employee = Employee::where('id', $user->employee->id)->first();

        $resource = new GetUserResource($employee);

        return response()->json($resource);
    }

    public function updateProfile(Request $request, Employee $employee): \Illuminate\Http\JsonResponse
    {
        try {
            $request->validate([
                'username' => 'required|string|max:255|unique:users,username,' . $employee->user_id,
                'password' => 'sometimes|nullable|string|min:6',
            ]);

            $user = User::where('id', $employee->user_id)->first();

            $oldUserData = $user->only(['username', 'password']);
            $oldEmployeeData = $employee->only(['img']);

            $updateData = [
                'username' => $request->username,
            ];

            // Password faqat kelsa va bo'sh bo'lmasa hash qilamiz
            if ($request->filled('password')) {
                $updateData['password'] = $this->hashPassword($request->password);
            }

            $user->update($updateData);

            if ($request->hasFile('img')) {
                $file = $request->file('img');
                $filename = time() . '.' . $file->getClientOriginalExtension();
                $file->storeAs('/images/', $filename);
                $employee->img = 'images/' . $filename;
                $employee->save();
            }

            Log::add(
                auth()->id(),
                "Profil ma'lumotlari yangilandi",
                'edit',
                [
                    'username' => $oldUserData['username'],
                    'img' => $oldEmployeeData['img'],
                ],
                [
                    'username' => $user->username,
                    'img' => $employee->img,
                ]
            );

            return response()->json(['message' => 'Profile updated successfully']);
        } catch (\Exception $exception) {
            return response()->json(['error' => 'Failed to update profile: ' . $exception->getMessage()], 500);
        }
    }

    protected function hashPassword($password): string
    {
        $options = ['cost' => 12];
        return password_hash($password, PASSWORD_BCRYPT, $options);
    }

    public function show(User $user): \Illuminate\Http\JsonResponse
    {
        $employee = Employee::where('user_id', $user->id)->first();

        if (!$employee) {
            return response()->json(['error' => 'Employee not found'], 404);
        }

        $resource = new GetUserResource($employee);

        return response()->json($resource);
    }

    public function storeIssue(Request $request): \Illuminate\Http\JsonResponse
{
    try {
        $request->validate([
            'description' => 'required|string|max:255',
            'image' => 'sometimes|nullable|image|max:20480',
        ]);

        $filename = null;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = time() . '.' . $file->getClientOriginalExtension();
            $file->storeAs('issues', $filename);
        }

        $issue = Issue::create([
            'user_id' => auth()->id(),
            'description' => $request->description,
            'image' => 'issues/' . ($filename ?? null),
        ]);

        Log::add(
            auth()->id(),
            "Yangi muammo qo'shildi",
            'create',
            [],
            [
                'description' => $request->description,
                'image' => $filename ? 'issues/' . $filename : null,
            ]
        );

        // Telegramga yuborish
        $user = auth()->user();
        $message = "<b>🛠 Yangi muammo bildirildi!</b>\n\n"
            . "👤 Foydalanuvchi: {$user->employee->name} ({$user->role->name})\n"
            . "📝 Tavsif: {$request->description}";

        $botToken = env('ISSUE_BOT_TOKEN');
        $chatId = env('TELEGRAM_GROUP_CHAT_ID');

        if ($filename) {
            // Agar rasm bo‘lsa — rasmni yuborish
            $photoPath = storage_path("app/public/issues/" . $filename);

            $response = Http::attach(
                'photo', file_get_contents($photoPath), $filename
            )->post("https://api.telegram.org/bot{$botToken}/sendPhoto", [
                'chat_id' => $chatId,
                'caption' => $message,
                'parse_mode' => 'HTML',
            ]);
        } else {
            // Agar rasm bo‘lmasa — oddiy xabar yuborish
            $response = Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'HTML',
            ]);
        }

        if ($response->successful()) {
            return response()->json([
                'message' => 'Fikringiz uchun rahmat! Muammo yuborildi.',
            ], 201);
        } else {
            return response()->json([
                'error' => 'Failed to send issue to Telegram: ' . $response->body()
            ], 500);
        }

    } catch (\Exception $exception) {
        return response()->json([
            'error' => 'Failed to report issue: ' . $exception->getMessage()
        ], 500);
    }
}


}