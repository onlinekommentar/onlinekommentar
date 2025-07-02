<?php

namespace App\Api\Services;

use App\Api\Resources\Commentary;
use Illuminate\Support\Collection;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Extensions\Pagination\LengthAwarePaginator;
use Statamic\Facades\Entry;
use Statamic\Facades\Search;

class CommentaryService
{
    public static function all(
        $search = null,
        $legislativeAct = null,
        $language = 'en',
        $sort = null,
        $page = 1,
    ): LengthAwarePaginator {
        $language = $language ?: 'en';

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
            default => $query->orderBy('title', 'asc'),
        };

        $perPage = config('api-platform.defaults.pagination_items_per_page', 50);

        return $query
            ->paginate($perPage, ['*'], 'page', $page ?? 1)
            ->through(fn ($entry) => static::transformEntry($entry));
    }

    public static function find(string $id): ?Commentary
    {
        $entry = Entry::query()
            ->where('collection', 'commentaries')
            ->where('blueprint', 'commentary')
            ->where('status', 'published')
            ->where('id', $id)
            ->first();

        return static::transformEntry($entry);
    }

    protected static function transformEntry(?EntryContract $entry): ?Commentary
    {
        if (! $entry) {
            return null;
        }

        return new Commentary(
            id: $entry->id,
            title: $entry->title,
            slug: $entry->slug,
            date: $entry->date()?->format('Y-m-d'),
            language: $entry->locale,
            authors: static::transformUsers($entry->assigned_authors)->all(),
            editors: static::transformUsers($entry->assigned_editors)->all(),
            legislative_act: static::transformLegalDomain($entry->value('legal_domain')),
            suggested_citation_long: $entry->suggested_citation_long,
            suggested_citation_short: $entry->suggested_citation_short,
            content: $entry->content,
            legal_text: $entry->legal_text,
            pdf_download_urls: [
                route('commentaries.print', ['locale' => $entry->locale, 'commentarySlug' => $entry->slug, 'text' => 'md']),
                route('commentaries.print', ['locale' => $entry->locale, 'commentarySlug' => $entry->slug, 'text' => 'lg']),
            ],
            additional_document_urls: static::transformAssets($entry->additional_documents)->all(),
        );
    }

    protected static function transformUsers($users): Collection
    {
        if (! $users) {
            return collect();
        }

        return collect($users)
            ->map(fn ($user) => [
                'id' => $user->id(),
                'name' => $user->get('name'),
            ]);
    }

    protected static function transformAssets($assets): Collection
    {
        if (! $assets) {
            return collect();
        }

        return collect($assets)
            ->map(fn ($asset) => $asset->absoluteUrl());
    }

    protected static function transformLegalDomain($legalDomain): ?array
    {
        if (! $legalDomain) {
            return null;
        }

        $legalDomain = Entry::find($legalDomain);
        if (! $legalDomain) {
            return null;
        }

        return [
            'id' => $legalDomain->id,
            'title' => $legalDomain->title,
        ];
    }
}
