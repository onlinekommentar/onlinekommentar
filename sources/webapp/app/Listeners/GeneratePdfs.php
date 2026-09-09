<?php

namespace App\Listeners;

use App\Jobs\GenerateCommentaryPdf;
use App\Jobs\GenerateLegalDomainPdf;
use App\Services\CommentaryTree;
use Illuminate\Support\Facades\Storage;
use Statamic\Events\EntryDeleting;
use Statamic\Events\EntrySaved;

class GeneratePdfs
{
    protected $sizes = ['md', 'lg'];

    public function handle(EntrySaved|EntryDeleting $event): void
    {
        $entry = $event->entry;

        if ($entry->collectionHandle() !== 'commentaries') {
            return;
        }

        $locale = $entry->locale();
        $slug = $entry->slug();
        $blueprint = $entry->blueprint()->handle();

        if ($blueprint === 'commentary') {
            $this->handleCommentary($event, $entry, $locale, $slug);
        } elseif ($blueprint === 'legal_domain') {
            $this->handleLegalDomain($event, $entry, $locale, $slug);
        } else {
            $this->handleAncestor($entry, $locale);
        }
    }

    protected function handleCommentary(EntrySaved|EntryDeleting $event, $entry, string $locale, string $slug): void
    {
        $disk = Storage::disk('pdf');

        foreach ($this->sizes as $size) {
            $disk->delete("commentary/{$locale}/{$size}/{$slug}.pdf");

            if ($event instanceof EntrySaved) {
                GenerateCommentaryPdf::dispatch($entry->id(), $locale, $size);
            }
        }

        $this->handleAncestor($entry, $locale);
    }

    protected function handleAncestor($entry, string $locale): void
    {
        $ancestor = CommentaryTree::findLegalDomainAncestor($entry, $locale);

        if (! $ancestor) {
            return;
        }

        Storage::disk('pdf')->deleteDirectory("legal-domain/{$locale}/{$ancestor->slug()}");

        GenerateLegalDomainPdf::dispatch($ancestor->id(), $locale);
    }

    protected function handleLegalDomain(EntrySaved|EntryDeleting $event, $entry, string $locale, string $slug): void
    {
        Storage::disk('pdf')->deleteDirectory("legal-domain/{$locale}/{$slug}");

        if ($event instanceof EntrySaved) {
            GenerateLegalDomainPdf::dispatch($entry->id(), $locale);
        }
    }
}
