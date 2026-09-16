<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Statamic\Facades\Entry;
use Textandbytes\Converter\Converter;

class GenerateCommentaryPdf implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 360;

    public $uniqueFor = 3600;

    public function __construct(
        protected string $entryId,
        protected string $locale,
        protected string $size = 'md',
    ) {}

    public function uniqueId(): string
    {
        return "commentary:{$this->locale}:{$this->size}:{$this->entryId}";
    }

    public function handle(): void
    {
        $entry = Entry::find($this->entryId);

        if (! $entry) {
            return;
        }

        app()->setLocale($this->locale);

        $converter = new Converter;
        $disk = Storage::disk('pdf');
        $slug = $entry->slug();

        $file = $converter->entryToHtmlPdf($entry, ['text' => $this->size]);
        $disk->put("commentary/{$this->locale}/{$this->size}/{$slug}.pdf", file_get_contents($file));
        @unlink($file);
    }
}
