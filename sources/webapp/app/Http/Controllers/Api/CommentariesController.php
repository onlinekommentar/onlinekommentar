<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommentaryResource;
use Illuminate\Http\Request;
use Statamic\Facades\Entry;
use Statamic\Facades\Search;

class CommentariesController extends Controller
{
    public function index(Request $request)
    {
        $language = $request->get('language', 'en');
        $search = $request->get('search');
        $legislativeAct = $request->get('legislative_act');
        $sort = $request->get('sort');
        $page = $request->get('page', 1);

        $query = $search
            ? Search::index('default', $language)
                ->ensureExists()
                ->search($search)
            : Entry::query();

        $query
            ->where('collection', 'commentaries')
            ->where('blueprint', 'commentary')
            ->where('status', 'published')
            ->where('site', $language)
            ->when($legislativeAct, function ($query) use ($legislativeAct) {
                $query->where('legal_domain', $legislativeAct);
            });

        match ($sort) {
            'title' => $query->orderBy('title', 'asc'),
            '-title' => $query->orderBy('title', 'desc'),
            'date' => $query->orderBy('date', 'asc'),
            '-date' => $query->orderBy('date', 'desc'),
            default => $query->orderBy('date', 'desc'),
        };

        $paginator = $query->paginate(50, ['*'], 'page', $page);

        return CommentaryResource::collection($paginator);
    }

    public function show(Request $request, string $id)
    {
        $entry = Entry::query()
            ->where('collection', 'commentaries')
            ->where('blueprint', 'commentary')
            ->whereStatus('published')
            ->where('id', $id)
            ->first();

        if (! $entry) {
            abort(404);
        }

        return CommentaryResource::make($entry);
    }
}
