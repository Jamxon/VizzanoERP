<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateEmployeeStatus
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle($request, Closure $next)
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized: Token is invalid or missing'], 401);
        }

        if (!$user->employee) {
            return response()->json(['message' => 'Unauthorized: Employee data is missing'], 401);
        }

        if ($user->employee->status === 'kicked') {
            return response()->json(['message' => 'You are kicked from the company'], 401);
        }

        return $next($request);
    }

}
