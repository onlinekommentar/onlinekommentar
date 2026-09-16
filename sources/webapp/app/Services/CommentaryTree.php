<?php

namespace App\Services;

use Illuminate\Support\Collection as SupportCollection;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection;

class CommentaryTree
{
    public static function findLegalDomainAncestor(Entry $entry, string $locale): ?Entry
    {
        $tree = Collection::findByHandle('commentaries')
            ->structure()
            ->in($locale);

        $page = $tree->find($entry->id());

        if (! $page) {
            return null;
        }

        $current = $page->parent();

        while ($current) {
            if ($current->entry() && $current->entry()->blueprint()->handle() === 'legal_domain') {
                return $current->entry();
            }
            $current = $current->parent();
        }

        return null;
    }

    public static function getCommentariesForLegalDomain(Entry $legalDomainEntry, string $locale): SupportCollection
    {
        $tree = Collection::findByHandle('commentaries')
            ->structure()
            ->in($locale);

        $page = $tree->find($legalDomainEntry->id());

        if (! $page) {
            return collect();
        }

        return $page->flattenedPages()
            ->filter(fn ($p) => $p->entry())
            ->filter(fn ($p) => $p->entry()->blueprint()->handle() === 'commentary')
            ->filter(fn ($p) => $p->entry()->published())
            ->map(fn ($p) => $p->entry())
            ->values();
    }

    public static function getLegalDomainEntries(string $locale): SupportCollection
    {
        $tree = Collection::findByHandle('commentaries')
            ->structure()
            ->in($locale);

        return $tree->pages()->all()
            ->filter(fn ($page) => $page->entry())
            ->filter(fn ($page) => $page->entry()->blueprint()->handle() === 'legal_domain')
            ->map(fn ($page) => $page->entry())
            ->values();
    }
}
