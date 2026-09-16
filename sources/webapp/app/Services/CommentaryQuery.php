<?php

namespace App\Services;

use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Entry;
use Statamic\Facades\Search;

class CommentaryQuery
{
    public const PER_PAGE = 50;

    public const CACHE_SECONDS = 600;

    public const LANGUAGES = ['de', 'en', 'fr', 'it'];

    public const SORTS = ['title', '-title', 'date', '-date'];

    public static function normalise(array $arguments): array
    {
        return [
            'search' => $arguments['search'] ?? null,
            'language' => $arguments['language'] ?? 'en',
            'legislative_act' => $arguments['legislative_act'] ?? null,
            'sort' => $arguments['sort'] ?? '-date',
            'page' => (int) ($arguments['page'] ?? 1),
        ];
    }

    public static function search(array $arguments): LengthAwarePaginator
    {
        $arguments = self::normalise($arguments);

        $query = $arguments['search']
            ? Search::index('default', $arguments['language'])
                ->ensureExists()
                ->search($arguments['search'])
            : Entry::query();

        $query
            ->where('collection', 'commentaries')
            ->where('blueprint', 'commentary')
            ->where('site', $arguments['language'])
            ->when($arguments['legislative_act'], function ($query) use ($arguments) {
                $query->where('legal_domain', $arguments['legislative_act']);
            });

        $arguments['search']
            ? $query->where('status', 'published')
            : $query->whereStatus('published');

        match ($arguments['sort']) {
            'title' => $query->orderBy('title', 'asc'),
            '-title' => $query->orderBy('title', 'desc'),
            'date' => $query->orderBy('date', 'asc'),
            default => $query->orderBy('date', 'desc'),
        };

        return $query
            ->paginate(self::PER_PAGE, ['*'], 'page', $arguments['page'])
            ->appends(collect($arguments)->except('page')->whereNotNull()->all());
    }

    public static function cachedSearch(string $surface, array $arguments, Closure $shape)
    {
        $arguments = self::normalise($arguments);

        return Cache::remember(
            "commentary-search:{$surface}:".md5(json_encode($arguments)),
            self::CACHE_SECONDS,
            fn () => $shape(self::search($arguments)),
        );
    }

    public static function find(string $id): ?EntryContract
    {
        return Entry::query()
            ->where('collection', 'commentaries')
            ->where('blueprint', 'commentary')
            ->whereStatus('published')
            ->where('id', $id)
            ->first();
    }
}
