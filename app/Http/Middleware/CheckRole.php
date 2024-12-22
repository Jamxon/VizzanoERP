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
        // Foydalanuvchi tizimga kirganini tekshiramiz
        if (!Auth::check()) {
            return response('Unauthorized', 401); // Foydalanuvchi tizimga kirgan bo'lishi kerak
        }

        // Foydalanuvchi rolini tekshiramiz
        $user = Auth::user();
        if ($user->role->name !== $role) {
            return response('Not allowed'." bu rol $role uchun, san esa ".$user->role->name." bo'lib kirmoqchi bo'lyapsan", 403); // Foydalanuvchi berilgan rolni bajarishi kerak
        }

        return $next($request);
    }
}
