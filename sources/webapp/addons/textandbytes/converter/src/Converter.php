<?php

namespace Textandbytes\Converter;

use Gotenberg\Gotenberg;
use Gotenberg\Stream;
use Pontedilana\PhpWeasyPrint\Pdf;
use Statamic\Support\Str;
use Statamic\View\View;
use Textandbytes\Converter\Marks\ParagraphNumber;
use Textandbytes\Converter\Nodes\Cleaner;
use Textandbytes\Converter\Nodes\Footnote;
use Tiptap\Editor;
use Tiptap\Marks;
use Tiptap\Nodes;
use TOC\MarkupFixer;
use TOC\TocGenerator;

class Converter
{
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
        if ($entry->collection()->handle() !== 'commentaries') {
            throw new \Exception('Entry is not a commentary');
        }

        $data = [
            [
                'type' => 'okTitle',
                'label' => 'Commentary on',
                'text' => $entry->title,
            ],
            [
                'type' => 'okSummary',
                'lines' => [
                    'A commentary by '.$entry->assigned_authors->pluck('name')->join(', '),
                    'Edited by '.$entry->assigned_editors->pluck('name')->join(', '),
                ],
            ],
            [
                'type' => 'okSuggestedCitationLong',
                'label' => 'Sugegsted Citation',
                'text' => $entry->suggested_citation_long,
            ],
            [
                'type' => 'okSuggestedCitationShort',
                'label' => 'Short Citation',
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
                'label' => 'Table of Contents',
            ],
            ...$entry->get('content') ?? [],
        ];

        $data = json_decode(json_encode($data));

        return (new WordRenderer)->render($data);
    }

    public function entryToWordPdf($entry)
    {
        $wordFile = $this->entryToWord($entry);

        $dir = storage_path('app');
        $request = Gotenberg::libreOffice(config('services.gotenberg.url'))
            ->convert(Stream::path($wordFile));
        $pdfFile = $dir.'/'.Gotenberg::save($request, $dir);

        unlink($wordFile);

        return $pdfFile;
    }

    public function entryToHtml($entry, $params = [])
    {
        $markupFixer = new MarkupFixer;
        $content = $markupFixer->fix($entry->content);

        $tocGenerator = new TocGenerator;
        $toc = $tocGenerator->getHtmlMenu($content);

        return (new View)
            ->template('commentaries.print')
            ->layout('print')
            ->cascadeContent($entry)
            ->with([
                'content' => $content,
                'toc' => $toc,
                ...$params,
            ])
            ->render();
    }

    public function entryToHtmlPdf($entry, $params = [])
    {
        $html = $this->entryToHtml($entry, $params);

        $pdfFile = storage_path('app').'/weasyprint-'.uniqid().'.pdf';

        $pdf = new Pdf(config('services.weasyprint.bin'));
        $pdf->generateFromHtml($html, $pdfFile);

        return $pdfFile;
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
