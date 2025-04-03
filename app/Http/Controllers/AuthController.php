<?php

namespace App\Http\Controllers;


use App\Models\Log;
use App\Models\User;
use http\Env\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function register(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'username' => 'required|string|min:6|unique:users,username|max:255',
            'password' => 'required|string|min:6',
            'role_id' => 'required|integer|exists:roles,id'
        ]);

        $hashedPassword = $this->hashPassword($request->password);

        $user = User::create([
            'username' => $request->username,
            'password' => $hashedPassword,
            'role_id' => $request->role_id,
        ]);

        $token = JWTAuth::fromUser($user);

        return response()->json([
            'message' => 'User registered successfully!',
            'user' => $user,
            'token' => $token
        ], 201);
    }

    /**
     * Django bilan mos keladigan parol hashlash funksiyasi.
     */
    protected function hashPassword($password): string
    {
        $options = [
            'cost' => 12, // Django'dagi `bcrypt.gensalt(rounds=12)` parametri bilan mos.
        ];
        return password_hash($password, PASSWORD_BCRYPT, $options);
    }

    public function login(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string|min:6',
        ]);

        $user = User::where('username', $request->username)->first();

        // Foydalanuvchi topilmasa yoki parol noto'g'ri bo'lsa log yozish
        if (!$user || !$this->checkDjangoPassword($request->password, $user->password) || ($user->employee->status == 'kicked')) {
            // Log yozish: Foydalanuvchi tizimga kira olmagan
            Log::add(
                $request->user() ? $request->user()->id : null,  // Agar foydalanuvchi mavjud bo'lsa, uning ID sini yozish
                'Tizimga kirishga urinish',
                null,
                [
                    'username' => $request->username,
                    'status' => 'failed',
                    'error' => 'Unauthorized',
                    'ip_address' => $request->ip(),  // IP manzilini ham yozish
                ]
            );

            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Foydalanuvchi muvaffaqiyatli tizimga kirganini loglash
        $token = JWTAuth::fromUser($user);

        Log::add(
            $user->id,  // Foydalanuvchi ID sini yozish
            'Muvaffaqiyatli tizimga kirish',
            null,
            [
                'username' => $request->username,
                'status' => 'success',
                'ip_address' => $request->ip(),  // IP manzilini yozish
                'token' => $token,
            ]
        );

        return response()->json([
            'token' => $token,
            'user' => $user
        ]);
    }
    /**
     * Django parollarni tekshirish funksiyasi
     */
    protected function checkDjangoPassword($plainPassword, $hashedPassword)
    {
        return password_verify($plainPassword, $hashedPassword);
    }
}
