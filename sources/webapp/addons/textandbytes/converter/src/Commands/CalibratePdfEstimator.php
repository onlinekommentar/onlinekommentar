<?php

namespace Textandbytes\Converter\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Statamic\Facades\Collection;
use Statamic\View\View;
use Textandbytes\Converter\Converter;

class CalibratePdfEstimator extends Command
{
    protected $signature = 'converter:calibrate-pdf-estimator';

    public function handle()
    {
        $converter = new Converter;
        $disk = Storage::disk('pdf');
        $totalWords = 0;
        $totalPages = 0;

        foreach (['de', 'en', 'fr', 'it'] as $locale) {
            $this->info("Processing locale: {$locale}");

            app()->setLocale($locale);

            $tree = Collection::findByHandle('commentaries')
                ->structure()
                ->in($locale);

            $entries = $tree
                ->flattenedPages()
                ->filter(fn ($page) => $page->entry())
                ->filter(fn ($page) => $page->entry()->blueprint()->handle() === 'commentary')
                ->filter(fn ($page) => $page->entry()->published())
                ->map(fn ($page) => $page->entry())
                ->values();

            if ($entries->isEmpty()) {
                $this->warn("No published commentaries found for locale: {$locale}");

                continue;
            }

            $this->info("Found {$entries->count()} entries");

            $entryData = [];

            foreach ($entries as $entry) {
                $totalWords += $converter->getEntryContentCounts($entry)['words'];

                $entryData[] = [
                    'rendered_content' => $converter->renderEntryContent($entry),
                ];
            }

            $html = (new View)
                ->template('commentaries.print-calibration')
                ->layout('print')
                ->with([
                    'entries' => $entryData,
                    'stylesheet' => 'print-legal-domain.css',
                    'locale' => $locale,
                    'text' => 'md',
                ])
                ->render();

            $this->info('Rendering PDF...');
            $disk->put("calibration/{$locale}.html", $html);
            $pdfFile = $converter->renderWeasyPdf($html, 3600);
            $pages = $this->countPdfPages($pdfFile);
            $totalPages += $pages;
            $disk->put("calibration/{$locale}.pdf", file_get_contents($pdfFile));
            @unlink($pdfFile);
            $this->info("  Pages: {$pages}");
        }

        $this->newLine();
        $this->info('=== Results ===');
        $this->newLine();

        $wordsPerPage = ($totalPages > 0 && $totalWords > 0)
            ? round($totalWords / $totalPages)
            : null;

        if ($wordsPerPage) {
            $this->info("Words per page: {$wordsPerPage}");
        } else {
            $this->warn('Could not calculate words per page (no text content found)');
        }

        $this->newLine();
        $this->info('Update Converter.php:');
        $this->newLine();
        $this->line("    const WORDS_PER_PAGE = {$wordsPerPage};");

        return Command::SUCCESS;
    }

    protected function countPdfPages(string $pdfFile): int
    {
        $output = shell_exec('pdfinfo '.escapeshellarg($pdfFile).' 2>/dev/null');

        if ($output && preg_match('/Pages:\s*(\d+)/', $output, $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }
}
