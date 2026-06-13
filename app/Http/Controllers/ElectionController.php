<?php

namespace App\Http\Controllers;

use Illuminate\View\View;
use App\Models\Election;

class ElectionController extends Controller
{
    public function single(Election $election): View
    {
        return view('election', ['election' => $election]);
    }
}
