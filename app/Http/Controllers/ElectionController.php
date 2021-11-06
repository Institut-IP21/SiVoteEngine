<?php

namespace App\Http\Controllers;

use App\Models\Election;

/**
 * @Controller(prefix="election")
 * @Middleware("web")
 */
class ElectionController extends Controller
{
    /**
     *  @Get("/{election}", as="election.show")
     */
    public function single(Election $election)
    {
        if ($election->mode === Election::MODE_SESSION) {
            throw new \Exception("Can not view SESSION elections this way ");
        }

        return view('election', ['election' => $election]);
    }
}
