<?php

namespace App\Http\Controllers;

use App\Models\Election;

class ElectionController extends Controller
{
    public function single(Election $election): \Illuminate\View\View
    {
        return view('election', ['election' => $election]);
    }
}
