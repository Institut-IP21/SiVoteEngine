<?php

use App\Http\Controllers\BallotController;
use App\Http\Livewire\Session;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('home');
});

Route::get('/session/{election}/ballot/{ballot}', Session::class)->middleware('web')->name('ballot.session');

Route::middleware('web')->prefix('election')->group(function () {
    Route::get('/{election}/ballot/{ballot}', [BallotController::class, 'view'])->name('ballot.view');
    Route::get('/{election}/ballot/{ballot}/preview', [BallotController::class, 'preview'])->name('ballot.preview');
    Route::post('/{election}/ballot/{ballot}', [BallotController::class, 'vote'])->name('ballot.vote');
    Route::post('/{election}/ballot/{ballot}/component', [BallotController::class, 'voteComponent'])->name('ballot.vote.component');
    Route::get('/{election}/ballot/{ballot}/result', [BallotController::class, 'result'])->name('ballot.result');
});
