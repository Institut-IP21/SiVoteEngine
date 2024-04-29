<?php

use App\Http\Controllers\BallotApiController;
use App\Http\Controllers\BallotComponentApiController;
use App\Http\Controllers\ElectionApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('api')->prefix('election')->group(function () {
    Route::get('/', [ElectionApiController::class, 'list'])->name('election.api.list')->middleware('can:viewAny,App\Models\Election');
    Route::post('/create', [ElectionApiController::class, 'create'])->name('election.api.create')->middleware('can:create,App\Models\Election');
    Route::get('/{election}', [ElectionApiController::class, 'read'])->name('election.api.read')->middleware('can:view,election');
    Route::post('/{election}', [ElectionApiController::class, 'update'])->name('election.api.update')->middleware('can:update,election');
    Route::delete('/{election}', [ElectionApiController::class, 'delete'])->name('election.api.delete')->middleware('can:delete,election');
});

Route::middleware('api')->prefix('election/{election}/ballot')->group(function () {
    Route::post('/create', [BallotApiController::class, 'create'])->name('ballot.api.create')->middleware('can:update,election');
    Route::get('/{ballot}', [BallotApiController::class, 'read'])->name('ballot.api.read')->middleware('can:view,election');
    Route::post('/{ballot}/update', [BallotApiController::class, 'update'])->name('ballot.api.update')->middleware('can:update,election');
    Route::post('/{ballot}/delete', [BallotApiController::class, 'delete'])->name('ballot.api.delete')->middleware('can:delete,election');
    Route::get('/{ballot}/result', [BallotApiController::class, 'result'])->name('ballot.api.result')->middleware('can:view,election');
    Route::get('/{ballot}/vote', [BallotApiController::class, 'votes'])->name('ballot.api.votes')->middleware('can:view,election');
    Route::get('/{ballot}/votes.csv', [BallotApiController::class, 'votesCsv'])->name('ballot.api.votes.csv')->middleware('can:view,election');
    Route::post('/{ballot}/activate', [BallotApiController::class, 'activate'])->name('ballot.api.activate')->middleware('can:update,election');
    Route::post('/{ballot}/deactivate', [BallotApiController::class, 'deactivate'])->name('ballot.api.deactivate')->middleware('can:update,election');
    Route::post('/{ballot}/switch-order', [BallotApiController::class, 'switchOrder'])->name('ballot.api.switch-order')->middleware('can:update,election');
});

Route::middleware('api')->prefix('election/{election}/ballot/{ballot}/component')->group(function () {
    Route::get('/', [BallotComponentApiController::class, 'list'])->name('componen.apit.list')->middleware('can:view,election');
    Route::post('/create', [BallotComponentApiController::class, 'create'])->name('component.api.create')->middleware('can:update,election');
    Route::get('/{component}', [BallotComponentApiController::class, 'read'])->name('component.api.read')->middleware('can:view,election');
    Route::post('/{component}', [BallotComponentApiController::class, 'update'])->name('component.api.update')->middleware('can:update,election');
    Route::delete('/{component}', [BallotComponentApiController::class, 'delete'])->name('component.api.delete')->middleware('can:update,election');
    Route::post('/{component}/activate', [BallotComponentApiController::class, 'activate'])->name('component.api.activate')->middleware('can:update,election');
    Route::post('/{component}/deactivate', [BallotComponentApiController::class, 'deactivate'])->name('component.api.deactivate')->middleware('can:update,election');
});

Route::fallback(function () {
    return response()->json(['message' => 'Not Found.'], 404);
})->name('api.fallback.404');
