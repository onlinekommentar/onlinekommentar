<?php

use App\Http\Controllers\Api\CommentariesController;
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

Route::prefix('/')
    ->name('api.commentaries.')
    ->middleware([Cache::class])
    ->group(function () {
        Route::get('/commentaries', [CommentariesController::class, 'index'])->name('index');
        Route::get('/commentaries/{id}', [CommentariesController::class, 'show'])->name('show');
    });
