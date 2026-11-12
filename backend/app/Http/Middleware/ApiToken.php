<?php

namespace App\Http\Middleware;

use App\Models\ApiToken as Token;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;

/** Bearer-token authentication (tokens are stored hashed; no extra packages needed). */
class ApiToken
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $plain = $request->bearerToken();
        $tok = $plain ? Token::where('token_hash', hash('sha256', $plain))->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->first() : null;
        $user = $tok ? User::where('id', $tok->user_id)->where('is_active', true)->first() : null;
        if (! $user) return response()->json(['error' => ['code' => 'unauthenticated', 'message' => 'লগইন করুন']], 401);
        if ($roles && ! in_array($user->role, $roles, true)) return response()->json(['error' => ['code' => 'forbidden', 'message' => 'এই কাজের অনুমতি নেই']], 403);
        $tok->forceFill(['last_used_at' => now()])->save();
        $request->setUserResolver(fn () => $user);
        return $next($request);
    }
}
