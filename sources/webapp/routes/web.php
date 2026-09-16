<?php

use App\Http\Controllers\Frontend\CommentariesController;
use App\Http\Controllers\Frontend\SearchController;
use App\Http\Controllers\Frontend\UsersController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Statamic\Facades\Entry;
use Statamic\Facades\User;

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

// local-only login bypass for development and automated testing
if (app()->environment('local')) {
    Route::get('/!/skip/{handle}', function ($handle) {
        abort_unless(
            Str::endsWith(request()->getHost(), ['.test', '.localhost', '.ts.net'])
                || in_array(request()->getHost(), ['localhost', '127.0.0.1', '::1']),
            404
        );

        $user = User::findByEmail("{$handle}@example.test");

        abort_unless($user, 404);

        auth()->login($user);

        return redirect('/');
    });
}

Route::get('/', function () {
    return redirect('/de');
});

// author and editor detail views
Route::get('{locale}/{usersType}/{slug}', [UsersController::class, 'show'])->whereIn('usersType', ['autoren', 'herausgeber']);

// legal domain PDF download
Route::get('{locale}/kommentare/{legalDomainSlug}/pdf', [CommentariesController::class, 'downloadLegalDomainPdf']);
// commentary revision detail view
Route::get('{locale}/kommentare/{commentarySlug}/versions/{versionTimestamp}', [CommentariesController::class, 'show']);
// commentary detail view
Route::get('{locale}/kommentare/{commentarySlug}', [CommentariesController::class, 'show']);
// commentary print view
Route::get('{locale}/kommentare/{commentarySlug}/print', [CommentariesController::class, 'print'])->name('commentaries.print');
// commentary print HTML preview (used to debug the PDF source markup)
Route::get('{locale}/kommentare/{commentarySlug}/print-preview', [CommentariesController::class, 'downloadPreview']);
// commentary revision comparison (previously published version – revision timestamp selected)
Route::get('{locale}/commentaries/{commentaryId}/revisions/{revisionTimestamp1}/compare/{revisionTimestamp2}/versions/{versionTimestamp}', [CommentariesController::class, 'compareRevisions']);
// commentary revision comparison (latest published version – no revision timestamp selected)
Route::get('{locale}/commentaries/{commentaryId}/revisions/{revisionTimestamp1}/compare/{revisionTimestamp2}', [CommentariesController::class, 'compareRevisions']);
// Search results view
Route::get('{locale}/search', [SearchController::class, 'index']);

if (app()->isLocal()) {
    Route::get('/sandbox', function () {
        
    });
}