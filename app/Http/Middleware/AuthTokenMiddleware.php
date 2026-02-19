<?php

namespace App\Http\Middleware;

use App\Models\Session;
use Closure;
use Illuminate\Http\Request;

class AuthTokenMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken() ?? $request->header('x-auth-token');

        if (!$token) {
            return response()->json(['error' => 'Token missing'], 401);
        }

        $session = Session::where('token', $token)
            ->where('expires_at', '>', now())
            ->first();

        if (!$session) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->attributes->set('auth_user_id', $session->user_id);

        return $next($request);
    }
}
