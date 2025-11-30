<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOtpIsVerified
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user || (is_null($user->phone_verified_at) && is_null($user->email_verified_at))) {
            return response()->json([
                'status' => 'error',
                'message' => 'Account not verified'
            ], 403);
        }

        return $next($request);
    }
}
