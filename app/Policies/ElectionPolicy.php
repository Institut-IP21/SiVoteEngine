<?php

namespace App\Policies;

use App\Models\Election;
use App\Models\ApiUser as User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;
use Illuminate\Http\Request;

class ElectionPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     *
     * @param  \App\Models\User  $user
     * @return mixed
     */
    public function viewAny(User $user)
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Election  $election
     * @return mixed
     */
    public function view(User $user, Election $election)
    {
        return $user->owner === $election->owner
            ? Response::allow()
            : Response::deny('You do not own this.', 403);
    }

    /**
     * Determine whether the user can create models.
     *
     * @param  \App\Models\User  $user
     * @return mixed
     */
    public function create(User $user)
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Election  $election
     * @return mixed
     */
    public function update(Request $request, User $user, Election $election)
    {
        if (! $request->hasValidSignature()) {
            return Response::allow();
        }
        return $user->owner === $election->owner
            ? Response::allow()
            : Response::deny('You do not own this.');
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Election  $election
     * @return mixed
     */
    public function delete(User $user, Election $election)
    {
        return $user->owner === $election->owner
            ? Response::allow()
            : Response::deny('You do not own this.');
    }
}
