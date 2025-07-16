<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, $role)
    {
        if (!Auth::check()) {
            return response('Unauthorized', 401);
        }

        $user = Auth::user();

        // Rol tekshiruvi
        if ($user->role->name !== $role) {
            return response('Not allowed. Bu rol ' . $role . ' uchun, sen esa ' . $user->role->name . ' bo‘lib kirmoqchi bo‘lyapsan', 403);
        }

        // Qo‘shimcha shartlar: agar rol 'tailor' bo‘lsa
        if ($role === 'tailor') {
            $employee = $user->employee;

            // 1. Soat 20:00 dan keyin bo‘lsa
            $now = now(); // Carbon instance
            if ($now->hour >= 20) {
                return response('Kech bo‘ldi! Soat 20:00 dan keyin tizimga kira olmaysiz.', 403);
            }

            // 2. Bugungi davomat mavjud emasmi?
            $hasAttendance = \App\Models\Attendance::where('employee_id', $employee->id)
                ->whereDate('date', $now->toDateString())
                ->exists();

            if (!$hasAttendance) {
                return response('Bugungi davomatingiz yo‘q. Iltimos, tizimga faqat davomat bo‘lsa kiring.', 403);
            }
        }

        return $next($request);
    }
}
