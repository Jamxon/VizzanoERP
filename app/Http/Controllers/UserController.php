<?php

namespace App\Http\Controllers;

use App\Http\Resources\GetUserResource;
use App\Models\Employee;
use App\Models\Log;
use App\Models\User;
use App\Models\Issue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $filename = time() . '.' . $file->getClientOriginalExtension();
                $file->storeAs('/issues/', $filename);
            }

            // Create a new issue
            Issue::create([
                'user_id' => auth()->id(),
                'description' => $request->description,
                'image' => 'issues/' . ($request->hasFile('image') ? $filename : null),
            ]);

            // Log the issue
            Log::add(
                auth()->id(),
                "Yangi muammo qo'shildi",
                'create',
                [],
                [
                    'description' => $request->description,
                    'image' => $request->hasFile('image') ? 'issues/' . $filename : null,
                ]
            );

            return response()->json(['message' => 'Fikringiz uchun rahmat! Muammo muvaffaqiyatli yuborildi. Tez orada bu muammoga yechim beriladi!'], 201);
        } catch (\Exception $exception) {
            return response()->json(['error' => 'Failed to report issue: ' . $exception->getMessage()], 500);
        }
        
    }
}