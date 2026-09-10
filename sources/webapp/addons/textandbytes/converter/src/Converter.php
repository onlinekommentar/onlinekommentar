<?php

namespace Textandbytes\Converter;

use Gotenberg\Gotenberg;
use Gotenberg\Stream;
use Illuminate\Support\Traits\Localizable;
use JackSleight\StatamicDistill\Facades\Distill;
use Pontedilana\PhpWeasyPrint\Pdf;
use Statamic\Entries\Entry;
use Statamic\Support\Str;
use Statamic\View\View;
use Textandbytes\Converter\Marks\ParagraphNumber;
use Textandbytes\Converter\Nodes\Cleaner;
use Textandbytes\Converter\Nodes\Footnote;
use Tiptap\Editor;
use Tiptap\Marks;
use Tiptap\Nodes;
use TOC\MarkupFixer;

class Converter
{
    use Localizable;

    // If the page layout changes run `herd php artisan converter:calibrate-pdf-estimator` to recalculate these numbers
    const WORDS_PER_PAGE = 310;

    public function htmlToProsemirror($html)
    {
        /* ProseMirror input must be UTF-8. Samples coming from tests will be
           but if we're processing a Word HTML file we need to convert it first */
        if (Str::contains($html, 'charset=windows-1252')) {
            $html = mb_convert_encoding($html, 'utf-8', 'windows-1252');
            $html = str_replace('charset=windows-1252', 'charset=utf-8', $html);
        }

        $html = Cleaner::preConvert($html);
        $html = ParagraphNumber::preConvert($html);

        $data = (new Editor([
            'extensions' => [
                new Marks\Bold,
                new Marks\Italic,
                new Marks\Link,
                new Marks\Superscript,
                new Marks\Underline,
                new Nodes\BulletList,
                new Nodes\HardBreak,
                new Nodes\Heading,
                new Nodes\ListItem,
                new Nodes\OrderedList,
                new Nodes\Paragraph,
                new Nodes\Document,
                new Nodes\Text,
                new Cleaner,
                new Footnote,
                new ParagraphNumber,
            ],
        ]))->setContent($html)->getDocument()['content'];

        $data = Cleaner::postConvert($data);

        return $data;
    }

    // public function prosemirrorToWord($data)
    // {
    //     $data = json_decode($data);

    //     return (new WordRenderer)->render($data);
    // }

    public function entryToWord($entry)
    {
        app()->setLocale($entry->locale());

        if ($entry->collection()->handle() !== 'commentaries') {
            throw new \Exception('Entry is not a commentary');
        }

        $data = [
            [
                'type' => 'okTitle',
                'label' => __('commentary_on'),
                'text' => $entry->title,
            ],
            [
                'type' => 'okSummary',
                'lines' => [
                    __('commentary_by').' '.$entry->assigned_authors->pluck('name')->join(', '),
                    __('edited_by').' '.$entry->assigned_editors->pluck('name')->join(', '),
                ],
            ],
            [
                'type' => 'okSuggestedCitationLong',
                'label' => __('suggested_citation'),
                'text' => $entry->suggested_citation_long,
            ],
            [
                'type' => 'okSuggestedCitationShort',
                'label' => __('short_citation'),
                'text' => $entry->suggested_citation_short,
            ],
            [
                'type' => 'okLegalText',
                'content' => [
                    ...$entry->get('legal_text') ?? [],
                ],
            ],
            [
                'type' => 'pageBreak',
            ],
            [
                'type' => 'tableOfContents',
                'label' => __('table_of_contents'),
            ],
            ...$entry->get('content') ?? [],
        ];

        $data = json_decode(json_encode($data));

        return (new WordRenderer)->render($data);
    }

    public function entryToWordPdf($entry)
    {
        $wordFile = $this->entryToWord($entry);

        try {
            $dir = storage_path('app');
            $request = Gotenberg::libreOffice(config('services.gotenberg.url'))
                ->convert(Stream::path($wordFile));

            return $dir.'/'.Gotenberg::save($request, $dir);
        } finally {
            unlink($wordFile);
        }
    }

    public function entryToHtml($entry, $params = [])
    {
        return $this->withLocale($entry->locale(), function () use ($entry, $params) {
            $content = $this->renderEntryContent($entry);

            $toc = (new TocBuilder)->build($content);

            return (new View)
                ->template('commentaries.print')
                ->layout('print')
                ->cascadeContent($entry)
                ->with([
                    'content' => $content,
                    'toc' => $toc,
                    'stylesheet' => 'print-commentary.css',
                    'locale' => $entry->locale(),
                    'generation_date' => now()->format('d.m.Y'),
                    ...$params,
                ])
                ->render();
        });
    }

    public function entryToHtmlPdf($entry, $params = [])
    {
        $html = $this->entryToHtml($entry, $params);

        return $this->renderWeasyPdf($html, 300);
    }

    public function entriesToHtml(array $entries, $tocPages, string $locale, int $volumeNumber, int $totalVolumes, string $generationDate, ?string $legalDomainTitle = null, ?string $lastChangeDate = null): string
    {
        return $this->withLocale($locale, function () use ($entries, $tocPages, $locale, $volumeNumber, $totalVolumes, $generationDate, $legalDomainTitle, $lastChangeDate) {
            $entryIds = collect($entries)->map(fn ($e) => $e->id())->all();

            $entryData = collect($entries)->map(function ($entry) {
                $html = $this->renderEntryContent($entry);
                $html = preg_replace('/<(h[1-6][^>]*)\bid="([^"]*)"/', '<$1id="'.$entry->id().'-$2"', $html);
                $html = preg_replace(
                    '/<span class="paragraph-nr">([^<]+)<\/span>/',
                    '<span class="paragraph-nr">$1</span><span class="paragraph-nr paragraph-nr--right">$1</span>',
                    $html
                );
                $toc = (new TocBuilder)->build($html);

                return array_merge($entry->toAugmentedArray(), [
                    'toc' => $toc,
                    'rendered_content' => $html,
                ]);
            })->all();

            $tocTree = $this->buildTocTree($tocPages, $entryIds);
            $tocHtml = $this->renderTocTree($tocTree);

            return (new View)
                ->template('commentaries.print-full')
                ->layout('print')
                ->with([
                    'entries' => $entryData,
                    'toc_html' => $tocHtml,
                    'volume_number' => $volumeNumber,
                    'total_volumes' => $totalVolumes,
                    'generation_date' => $generationDate,
                    'legal_domain_title' => $legalDomainTitle,
                    'last_change_date' => $lastChangeDate,
                    'stylesheet' => 'print-legal-domain.css',
                    'locale' => $locale,
                    'text' => 'md',
                ])
                ->render();
        });
    }

    public function entriesToHtmlPdf(array $entries, $tocPages, string $locale, int $volumeNumber, int $totalVolumes, string $generationDate, ?string $legalDomainTitle = null, ?string $lastChangeDate = null): string
    {
        $html = $this->entriesToHtml($entries, $tocPages, $locale, $volumeNumber, $totalVolumes, $generationDate, $legalDomainTitle, $lastChangeDate);

        return $this->renderWeasyPdf($html, 600);
    }

    public function renderEntryContent($entry): string
    {
        $html = (new View)
            ->template('commentaries.print-content')
            ->cascadeContent($entry)
            ->render();

        return (new MarkupFixer)->fix($html);
    }

    public function renderWeasyPdf(string $html, int $timeout = 30): string
    {
        $pdfFile = storage_path('app').'/weasyprint-'.uniqid().'.pdf';

        $pdf = new Pdf(config('services.weasyprint.bin'));
        $pdf->setTimeout($timeout);
        $pdf->setOption('pdf-variant', 'pdf/x-4');
        $pdf->setOption('full-fonts', true);
        $pdf->generateFromHtml($html, $pdfFile);

        return $pdfFile;
    }

    public function getEntryContentCounts(Entry $entry): array
    {
        preg_match_all('/\p{L}+/u', Distill::text($entry->augmentedValue('content')), $matches);

        return [
            'words' => count($matches[0]),
        ];
    }

    public function estimateEntryPages(Entry $entry): float
    {
        $counts = $this->getEntryContentCounts($entry);

        $pages = $counts['words'] / static::WORDS_PER_PAGE
            + 1    // entry title page
            + 2.5  // entry TOC
            + 1.5; // blank page padding for odd-page starts

        return max($pages, 1);
    }

    public static function estimateVolumeOverheadPages(): float
    {
        return 1   // volume title page
            + 1;   // volume TOC
    }

    protected function renderTocTree(array $items): string
    {
        $html = '<ol>';

        foreach ($items as $item) {
            if ($item['type'] === 'group') {
                $html .= '<li class="toc-group">'.e($item['title']);
                $html .= $this->renderTocTree($item['children']);
                $html .= '</li>';
            } else {
                $html .= '<li><a href="#entry-'.$item['id'].'">'.e($item['title']).'</a>';
                if (! empty($item['children'])) {
                    $html .= $this->renderTocTree($item['children']);
                }
                $html .= '</li>';
            }
        }

        $html .= '</ol>';

        return $html;
    }

    protected function buildTocTree($pages, array $entryIds): array
    {
        $tree = [];

        foreach ($pages->all() as $page) {
            $entry = $page->entry();

            if (! $entry || ! $entry->published()) {
                continue;
            }

            $blueprint = $entry->blueprint()->handle();
            $children = $this->buildTocTree($page->pages(), $entryIds);

            if ($blueprint === 'commentary' && in_array($entry->id(), $entryIds)) {
                $tree[] = [
                    'type' => 'entry',
                    'id' => $entry->id(),
                    'title' => $entry->get('title'),
                    'children' => $children,
                ];
            } elseif (! empty($children)) {
                $tree[] = [
                    'type' => 'group',
                    'title' => $entry->get('title'),
                    'children' => $children,
                ];
            }
        }

        return $tree;
    }

    protected function makeParagraph($text)
    {
        return [
            'type' => 'paragraph',
            'content' => [
                ['type' => 'text', 'text' => $text],
            ],
        ];
    }

    protected function makeHeading($text, $level)
    {
        return [
            'type' => 'heading',
            'attrs' => ['level' => $level],
            'content' => [
                ['type' => 'text', 'text' => $text],
            ],
        ];
    }
}
