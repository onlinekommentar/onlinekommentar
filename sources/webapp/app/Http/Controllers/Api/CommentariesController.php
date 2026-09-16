<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommentaryResource;
use App\Services\CommentaryQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CommentariesController extends Controller
{
    public function index(Request $request)
    {
        $validator = Validator::make($request->query(), [
            'search' => ['nullable', 'string', 'max:500'],
            'language' => ['nullable', 'string', 'in:'.implode(',', CommentaryQuery::LANGUAGES)],
            'legislative_act' => ['nullable', 'string'],
            'sort' => ['nullable', 'string', 'in:'.implode(',', CommentaryQuery::SORTS)],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        return CommentaryQuery::cachedSearch(
            'api',
            $validator->validated(),
            fn ($paginator) => CommentaryResource::collection($paginator)->response()->getData(true),
        );
    }

    public function show(Request $request, string $id)
    {
        $entry = CommentaryQuery::find($id);

        if (! $entry) {
            abort(404);
        }

        return CommentaryResource::make($entry)->detailed();
    }
}
