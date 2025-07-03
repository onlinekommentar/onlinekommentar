<?php

use App\Http\Controllers\Api\CommentaryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Statamic\StaticCaching\Middleware\Cache;

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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('json')
    ->name('api.json.commentaries.')
    ->middleware(['throttle:60,1', Cache::class])
    ->group(function () {
        Route::get('/commentaries', [CommentaryController::class, 'index'])->name('index');
        Route::get('/commentaries/{id}', [CommentaryController::class, 'show'])->name('show');
    });
