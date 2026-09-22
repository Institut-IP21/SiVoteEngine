<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureApiAdmin
{
    public function handle(Request $request, Closure $next): mixed
    {
        if ($request->attributes->get(ApiAuth::ADMIN_ATTRIBUTE) !== true) {
            return response(['error' => 'Admin token required.'], 403);
        }

        return $next($request);
    }
}
