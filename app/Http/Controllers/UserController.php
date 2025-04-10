<?php

namespace App\Http\Controllers;

use App\Http\Resources\GetUserResource;
use App\Models\Employee;
use App\Models\Log;
use App\Models\User;
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

    public function updateProfile(Request $request, Employee $employee)
    {
        try {
            $request->validate([
                'username' => 'required|string|max:255|unique:users,username,' . $employee->user_id,
                'password' => 'sometimes|string|min:6',
            ]);

            $user = User::where('id', $employee->user_id)->first();

            $oldUserData = $user->only(['username', 'password']);
            $oldEmployeeData = $employee->only(['img']);

            $user->update([
                'username' => $request->username,
                'password' => $this->hashPassword($request->password) ?: $oldUserData['password'],
            ]);

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
}