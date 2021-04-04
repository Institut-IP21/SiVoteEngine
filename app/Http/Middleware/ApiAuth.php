<?php

namespace App\Http\Middleware;

use App\Models\ApiUser;
use Closure;
use Illuminate\Support\Facades\Auth;

class ApiAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $auth = $request->header('Authorization');

        if (!$auth || !in_array($auth, config('app.api.authlist'))) {
            return response(['error' => 'No authorization or invalid.'], 401);
        }

        $owner = $request->header('Owner');

        if (!$owner) {
            return response(['error' => 'No owner.'], 403);
        }

        $user = new ApiUser();
        $user->owner = $owner;

        Auth::login($user);

        return $next($request);
    }
}
