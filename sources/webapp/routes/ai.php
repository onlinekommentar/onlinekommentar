<?php

use App\Mcp\Servers\CommentariesServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

Route::middleware('throttle:api')->group(function () {
    Mcp::web('mcp', CommentariesServer::class);
});
