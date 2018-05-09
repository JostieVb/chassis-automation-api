<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class CheckPermissions
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next, $permission)
    {
        if (Auth::user()->permissions != null) {
            $permissions = explode(',', Auth::user()->permissions);
            if (in_array($permission, $permissions)) {
                return $next($request);
            }
        }

        return response()->json(['message' => 'Unauthorized'], 401);
    }
}
