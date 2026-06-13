<?php

namespace App\Http\Middleware;

use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use Closure;

class ScopeRouteBindings
{
    /**
     * Ensure nested route models actually belong to their parent: a {ballot}
     * must belong to the {election} in the URL, and a {component} to the
     * {ballot}. Without this, a caller authorized on one election can reach a
     * ballot/component from another election (cross-tenant IDOR), because the
     * `can:` checks only authorize the {election}. 404 on mismatch — identical
     * to requesting an id that does not exist.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $route = $request->route();
        if ($route === null) {
            return $next($request);
        }

        $election  = $route->parameter('election');
        $ballot    = $route->parameter('ballot');
        $component = $route->parameter('component');

        if ($election instanceof Election && $ballot instanceof Ballot
            && (string) $ballot->election_id !== (string) $election->id) {
            abort(404);
        }

        if ($ballot instanceof Ballot && $component instanceof BallotComponent
            && (string) $component->ballot_id !== (string) $ballot->id) {
            abort(404);
        }

        return $next($request);
    }
}
