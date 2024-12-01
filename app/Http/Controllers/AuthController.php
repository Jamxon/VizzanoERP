<?php

namespace App\Http\Controllers;


use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
//    public function register(Request $request)
//    {
//        // Kiruvchi ma'lumotlarni tasdiqlash
//        $request->validate([
//            'username' => 'required|string|unique:users,username|max:255',
//            'password' => 'required|string|min:6',
//            'role_id' => 'required|integer|exists:roles,id'
//        ]);
//
//        // Foydalanuvchini yaratish
//        $user = User::create([
//            'username' => $request->username,
//            'password' => Hash::make($request->password),
//            'role_id' => $request->role_id,
//        ]);
//
//        // JWT tokenini yaratish
//        $token = JWTAuth::fromUser($user);
//
//        return response()->json([
//            'message' => 'User registered successfully!',
//            'user' => $user,
//            'token' => $token
//        ], 201);
//    }


    // Login methodi
//    public function login(Request $request)
//    {
//        $request->validate([
//            'username' => 'required|string',
//            'password' => 'required|string|min:6',
//        ]);
//        $credentials = $request->only('username', 'password');
//
//            if (!$token = JWTAuth::attempt($credentials)) {
//                return response()->json(['error' => 'Unauthorized'], 401);
//            }
//
//            return response()->json([
//                'token' => $token,
//                'user' => auth()->user()
//            ]);
//    }

    public function register(Request $request)
    {
        // Kiruvchi ma'lumotlarni tasdiqlash
        $request->validate([
            'username' => 'required|string|unique:users,username|max:255',
            'password' => 'required|string|min:6',
            'role_id' => 'required|integer|exists:roles,id'
        ]);

        // Parolni Django kabi bcrypt yordamida hashlash
        $hashedPassword = $this->hashPassword($request->password);

        // Foydalanuvchini yaratish
        $user = User::create([
            'username' => $request->username,
            'password' => $hashedPassword,
            'role_id' => $request->role_id,
        ]);

        // JWT tokenini yaratish
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
    protected function hashPassword($password)
    {
        $options = [
            'cost' => 12, // Django'dagi `bcrypt.gensalt(rounds=12)` parametri bilan mos.
        ];
        return password_hash($password, PASSWORD_BCRYPT, $options);
    }


    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string|min:6',
        ]);

        $user = User::where('username', $request->username)->first();



        if (!$user || !$this->checkDjangoPassword($request->password, $user->password) || $user->employee->status == 'kicked') {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $token = JWTAuth::fromUser($user);

        return response()->json([
            'token' => $token,
            'user' => $user
        ]);
    }

    /**
     * Django parollarni tekshirish funksiyasi.
     */
    protected function checkDjangoPassword($plainPassword, $hashedPassword)
    {
        return password_verify($plainPassword, $hashedPassword);
    }
}
