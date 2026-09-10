<?php

namespace App\Mcp\Tools;

use App\Http\Resources\CommentaryResource;
use App\Services\CommentaryQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Statamic\Contracts\Search\Result as SearchResult;

#[Name('search_commentaries')]
#[Title('Search commentaries')]
#[Description('Search Onlinekommentar\'s legal commentaries by full text query, optionally narrowed to a language, a legislative act or a sort order. Returns a page of matching commentaries with a snippet of the matching text. Use the returned id with get_commentary to read one in full.')]
class SearchCommentaries extends Tool
{
    public function handle(Request $request): Response
    {
        $arguments = $request->validate([
            'search' => ['nullable', 'string', 'max:500'],
            'language' => ['nullable', 'string', 'in:'.implode(',', CommentaryQuery::LANGUAGES)],
            'legislative_act' => ['nullable', 'string'],
            'sort' => ['nullable', 'string', 'in:'.implode(',', CommentaryQuery::SORTS)],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $results = CommentaryQuery::cachedSearch('mcp', $arguments, $this->results(...));

        return Response::text(json_encode($results, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()
                ->description('Full text query, matched against commentary titles, authors, editors, legal text and content. Omit to list commentaries without searching.'),
            'language' => $schema->string()
                ->enum(CommentaryQuery::LANGUAGES)
                ->default('en')
                ->description('Language edition to search. Each language is a separate commentary with its own text.'),
            'legislative_act' => $schema->string()
                ->description('Restrict results to one legislative act, given as the id returned in a result\'s legislative_act.'),
            'sort' => $schema->string()
                ->enum(CommentaryQuery::SORTS)
                ->default('-date')
                ->description('Sort order. A leading minus reverses it; the default is newest first.'),
            'page' => $schema->integer()
                ->min(1)
                ->default(1)
                ->description('Page of results to return, '.CommentaryQuery::PER_PAGE.' per page.'),
        ];
    }

    protected function results($paginator): array
    {
        return [
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'results' => collect($paginator->items())->map($this->transform(...))->all(),
        ];
    }

    protected function transform($result): array
    {
        $commentary = CommentaryResource::make($result)->resolve(request());

        return [
            'id' => $commentary['id'],
            'title' => $commentary['title'],
            'authors' => $commentary['authors'],
            'editors' => $commentary['editors'],
            'legislative_act' => $commentary['legislative_act'],
            'date' => $commentary['date'],
            'url' => $commentary['html_link'],
            'snippet' => $this->snippet($result),
        ];
    }

    protected function snippet($result): ?string
    {
        if (! $result instanceof SearchResult) {
            return null;
        }

        $snippet = $result->getRawResult()['_snippetResult']['combined']['value'] ?? null;

        if (! is_string($snippet)) {
            return null;
        }

        return trim(html_entity_decode(strip_tags($snippet), ENT_QUOTES | ENT_HTML5)) ?: null;
    }
}
