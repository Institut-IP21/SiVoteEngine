<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use App\Models\ApiUser;
use Closure;
use Illuminate\Support\Facades\Auth;
use Ramsey\Uuid\Uuid;

class ApiAuth
{
    public const ADMIN_ATTRIBUTE = 'api_admin';

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $auth = (string) $request->header('Authorization');

        $isTenant = $this->matchesAny($auth, (array) config('app.api.authlist'));
        $isAdmin = $this->matchesAny($auth, $this->adminList());

        if ($auth === '' || (!$isTenant && !$isAdmin)) {
            return response(['error' => 'No authorization or invalid.'], 401);
        }

        $request->attributes->set(self::ADMIN_ATTRIBUTE, $isAdmin);

        $owner = (string) $request->header('Owner');

        if ($owner === '') {
            return response(['error' => 'No owner.'], 403);
        }

        if (!Uuid::isValid($owner)) {
            return response(['error' => 'Invalid owner.'], 403);
        }

        $user = new ApiUser();
        $user->owner = $owner;

        Auth::login($user);

        return $next($request);
    }

    /**
     * No early return, so timing doesn't reveal which/how-many tokens matched.
     *
     * @param array<int, string> $list
     */
    private function matchesAny(string $value, array $list): bool
    {
        $match = false;
        foreach ($list as $candidate) {
            if (hash_equals((string) $candidate, $value)) {
                $match = true;
            }
        }
        return $match;
    }

    /** @return array<int, string> */
    private function adminList(): array
    {
        /** @var array<int, string> $admin */
        $admin = array_values(array_filter((array) config('app.api.admin_authlist')));

        if ($admin !== []) {
            return $admin;
        }

        return (array) config('app.api.authlist');
    }
}
