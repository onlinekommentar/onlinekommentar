<?php

namespace Textandbytes\Converter\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PurgePdfs extends Command
{
    protected $signature = 'converter:purge-pdfs';

    protected $description = 'Delete all stored PDFs so they regenerate on their next request';

    public function handle()
    {
        $disk = Storage::disk('pdf');

        collect($disk->directories())->each(fn ($directory) => $disk->deleteDirectory($directory));

        $this->info('Purged all stored PDFs.');

        return Command::SUCCESS;
    }
}
