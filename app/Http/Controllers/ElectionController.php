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
        return view('election', ['election' => $election]);
    }
}
