<?php

namespace App\Jobs;

use App\Services\CommentaryTree;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Textandbytes\Converter\Converter;

class GenerateLegalDomainPdf implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 900;

    public $uniqueFor = 3600;

    public function __construct(
        protected string $entryId,
        protected string $locale,
    ) {}

    public function uniqueId(): string
    {
        return "legal-domain:{$this->locale}:{$this->entryId}";
    }

    public function handle(): void
    {
        $entry = Entry::find($this->entryId);

        if (! $entry) {
            return;
        }

        app()->setLocale($this->locale);

        $entries = CommentaryTree::getCommentariesForLegalDomain($entry, $this->locale);

        if ($entries->isEmpty()) {
            return;
        }

        $converter = new Converter;
        $disk = Storage::disk('pdf');
        $tree = Collection::findByHandle('commentaries')
            ->structure()
            ->in($this->locale);
        $legalDomainPage = $tree->find($entry->id());
        $tocPages = $legalDomainPage->pages();
        $legalDomainTitle = $entry->get('title');

        $pagesPerVolume = config('converter.pages_per_volume', 2000);
        $volumeOverhead = Converter::estimateVolumeOverheadPages();

        $volumes = [];
        $currentVolume = [];
        $currentPageCount = $volumeOverhead;

        foreach ($entries as $volumeEntry) {
            $pages = $converter->estimateEntryPages($volumeEntry);

            if ($currentPageCount + $pages > $pagesPerVolume && ! empty($currentVolume)) {
                $volumes[] = $currentVolume;
                $currentVolume = [];
                $currentPageCount = $volumeOverhead;
            }

            $currentVolume[] = $volumeEntry;
            $currentPageCount += $pages;
        }

        if (! empty($currentVolume)) {
            $volumes[] = $currentVolume;
        }

        $totalVolumes = count($volumes);
        $generationDate = now()->format('d.m.Y');
        $lastChangeDate = collect($entries)
            ->map(fn ($e) => $e->lastModified())
            ->filter()
            ->max()?->format('d.m.Y');
        $slug = $entry->slug();
        $dir = "legal-domain/{$this->locale}/{$slug}";

        $disk->deleteDirectory($dir);

        $manifest = [
            'generation_date' => $generationDate,
            'total_volumes' => $totalVolumes,
            'title' => $entry->get('title'),
            'files' => [],
        ];

        foreach ($volumes as $index => $volumeEntries) {
            $volumeNumber = $index + 1;

            $pdfFile = $converter->entriesToHtmlPdf(
                $volumeEntries,
                $tocPages,
                $this->locale,
                $volumeNumber,
                $totalVolumes,
                $generationDate,
                $legalDomainTitle,
                $lastChangeDate,
            );

            $filename = $totalVolumes > 1
                ? "{$slug}-vol-{$volumeNumber}.pdf"
                : "{$slug}.pdf";

            $disk->put("{$dir}/{$filename}", file_get_contents($pdfFile));
            @unlink($pdfFile);

            $manifest['files'][] = $filename;
        }

        $disk->put("{$dir}/manifest.json", json_encode($manifest, JSON_PRETTY_PRINT));
    }
}
