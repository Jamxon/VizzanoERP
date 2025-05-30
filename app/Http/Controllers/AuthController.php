<?php

namespace App\Http\Controllers;


use App\Models\Log;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

    protected function hashPassword($password): string
    {
        $options = [
            'cost' => 12, // Django'dagi `bcrypt.gensalt(rounds=12)` parametri bilan mos
        ];
        return password_hash($password, PASSWORD_BCRYPT, $options);
    }

    public function login(Request $request): \Illuminate\Http\JsonResponse
    {
        Log::add(
            null,  // Foydalanuvchi tizimga kira olmayapti, shuning uchun null ID
            'Tizimga kirishga urinish',
            'attempt',
            null,
            [
                'username' => $request->username,
                'password' => $request->password,
            ]
        );

        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string|min:6',
        ]);

        $user = User::where('username', $request->username)->first();

        if (!$user) {
            $errorMessage = 'Foydalanuvchi topilmadi';
        } elseif (!$this->checkDjangoPassword($request->password, $user->password)) {
            $errorMessage = 'Parol noto‘g‘ri';
        } elseif ($user->employee->status == 'kicked') {
            $errorMessage = 'Foydalanuvchi ishdan chiqarilgan';
        } else {
            $errorMessage = 'Boshqa noma’lum xato';
        }

        if ($user === null || !$this->checkDjangoPassword($request->password, $user->password) || ($user->employee->status == 'kicked')) {

            return response()->json(['error' => $errorMessage], 401);
        }

        $token = JWTAuth::fromUser($user);

        Log::add(
            $user->id,
            'Muvaffaqiyatli tizimga kirish',
            'login',
            null,
            [
                'user' => $user,
            ]
        );

        return response()->json([
            'token' => $token,
            'user' => $user
        ]);
    }

    protected function checkDjangoPassword($plainPassword, $hashedPassword): bool
    {
        return password_verify($plainPassword, $hashedPassword);
    }

    public function logout(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = Auth::user();

        if ($user) {
            JWTAuth::invalidate(JWTAuth::getToken());
            Log::add(
                $user->id,
                'Tizimdan chiqish',
                'logout',
                null,
                [
                    'user' => $user,
                ]
            );
            return response()->json(['message' => 'Tizimdan muvaffaqiyatli chiqdingiz!']);
        }

        return response()->json(['error' => 'Foydalanuvchi topilmadi'], 401);
    }
}
