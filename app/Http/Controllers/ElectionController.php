<?php

namespace App\Http\Controllers;

use App\Models\Election;

class ElectionController extends Controller
{
    public function single(Election $election)
    {
        return view('election', ['election' => $election]);
    }
}
