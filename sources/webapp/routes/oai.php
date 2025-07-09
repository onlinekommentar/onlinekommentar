<?php

use App\Http\Controllers\Oai\OaiController;
use Illuminate\Support\Facades\Route;
use Statamic\StaticCaching\Middleware\Cache;

/*
|--------------------------------------------------------------------------
| OAI Routes
|--------------------------------------------------------------------------
*/

Route::get('/', [OaiController::class, 'index'])
    ->name('oai')
    ->middleware([Cache::class]);
