<?php

namespace Textandbytes\Converter;

use Illuminate\Support\Arr;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\TblWidth;
use PhpOffice\PhpWord\Style\Font;
use PhpOffice\PhpWord\Style\Language;
use PhpOffice\PhpWord\Style\ListItem;
use Statamic\Support\Str;
use Tiptap\Editor;
use Tiptap\Extensions;
use Tiptap\Marks;

class WordRenderer
{
    protected $editor;

    protected $word;

    protected $margin = [
        'top' => 20,
        'right' => 20,
        'bottom' => 20,
        'left' => 20,
    ];

    protected $colors = [
        'brown' => '#f4e8d7',
    ];

    public function __construct()
    {
        Settings::setOutputEscapingEnabled(true);

        $this->word = new PhpWord();
        $this->word->getSettings()->setThemeFontLang(new Language(Language::DE_DE));
        $this->word->getSettings()->setUpdateFields(true);
        $this->defineStyles();

        $this->editor = new Editor([
            'extensions' => [
                new Extensions\StarterKit(),
                new Marks\Underline(),
                new Marks\Subscript(),
                new Marks\Superscript(),
                new Marks\Link(),
            ],
        ]);
    }

    protected function defineStyles()
    {
        $this->word->setDefaultFontName('Times New Roman');
        $this->word->setDefaultFontSize(12);

        $this->word->addTitleStyle(1, [
            'size' => 14,
            'allCaps' => true,
            'alignment' => Jc::START,
        ]);
        $this->word->addTitleStyle(2, [
            'size' => 14,
            'allCaps' => true,
            'alignment' => Jc::START,
        ]);
        $this->word->addTitleStyle(3, [
            'size' => 14,
            'alignment' => Jc::START,
        ]);
        $this->word->addTitleStyle(4, [
            'size' => 12,
            'alignment' => Jc::START,
        ]);
        $this->word->addTitleStyle(5, [
            'size' => 12,
            'alignment' => Jc::START,
        ]);
        $this->word->addTitleStyle(6, [
            'size' => 12,
            'alignment' => Jc::START,
        ]);

        $this->word->setDefaultParagraphStyle([
            'spacing' => $this->lineHeight(1.1),
            'alignment' => Jc::BOTH,
        ]);

        $this->word->addParagraphStyle('withNumber', [
            'spacing' => $this->lineHeight(1.1),
            'alignment' => Jc::BOTH,
            'indentation' => [
                // 'left' => $this->twip(5),
                'hanging' => $this->twip(7),
            ],
        ]);

        $this->word->addTableStyle('table', [
            'borderSize' => 1,
            'cellMargin' => $this->twip(1),
        ], [
            'bgColor' => 'EEEEEE',
        ]);

        $this->word->addTableStyle('table', [
            'size' => 10,
        ]);
    }

    public function render($data, $format = 'Word2007')
    {
        $this->renderSection($data, $this->word);

        $extension = match ($format) {
            'Word2007' => 'docx',
            'ODText' => 'odt',
            'HTML' => 'html',
            'PDF' => 'pdf',
        };

        $file = tempnam(sys_get_temp_dir(), 'PHPWord-').'.'.$extension;
        $this->word->save($file, $format);

        return $file;
    }

    protected function renderSection($nodes, $cursor)
    {
        $section = $cursor->addSection([
            'marginTop' => $this->twip($this->margin['top']),
            'marginRight' => $this->twip($this->margin['right']),
            'marginBottom' => $this->twip($this->margin['bottom']),
            'marginLeft' => $this->twip($this->margin['left']),
        ]);

        $footer = $section->addFooter();
        $footer->addPreserveText('{PAGE}/{NUMPAGES}', [
            'size' => 10,
        ], [
            'alignment' => Jc::END,
        ]);

        $this->renderNodes($nodes, $section);
    }

    protected function renderNodes($nodes, $cursor, ...$pass)
    {
        foreach ($nodes as $node) {
            $this->renderNode($node, $cursor, $pass);
        }
    }

    protected function renderNode($node, $cursor, $pass = [])
    {
        $method = 'render'.ucfirst(Str::camel($node->type));

        $this->$method($node, $cursor, ...$pass);
    }

    protected function renderHeading($node, $cursor, $blockStyle = [])
    {
        $text = collect($node->content ?? [])->map(fn ($node) => $node->text)->join('');

        $cursor->addTitle($text, $node->attrs->level ?? 1);
        $cursor->addTextBreak();
    }

    protected function renderParagraph($node, $cursor, $blockStyle = [], $textStyle = [])
    {
        if ($this->isParagraphWithNumber($node)) {
            return $this->renderParagraphWithNumber($node, $cursor);
        }

        $this->renderNodes($node->content ?? [], $cursor->addTextRun($blockStyle), $textStyle);
        $cursor->addTextBreak();
    }

    protected function renderParagraphWithNumber($node, $cursor)
    {
        $first = $node->content[0] ?? null;
        $second = $node->content[1] ?? null;

        $first->text = $first->text."\t";
        if ($second && $second->type === 'text') {
            $second->text = ltrim($second->text);
        }

        $this->renderNodes($node->content ?? [], $cursor->addTextRun('withNumber'));
        $cursor->addTextBreak();
    }

    protected function renderBulletList($node, $cursor, $level = 0)
    {
        $this->renderList($node, $cursor, $level, ['listType' => ListItem::TYPE_BULLET_FILLED]);
    }

    protected function renderOrderedList($node, $cursor, $level = 0)
    {
        $this->renderList($node, $cursor, $level, ['listType' => ListItem::TYPE_ALPHANUM]);
    }

    protected function renderList($node, $cursor, $level, $blockStyle)
    {
        foreach ($node->content ?? [] as $item) {
            $content = $item->content ?? [];
            $text = array_shift($content);
            $this->renderNodes($text->content ?? [], $cursor->addListItemRun($level, $blockStyle));
            $this->renderNodes($content ?? [], $cursor, $level + 1);
        }
        $cursor->addTextBreak();
    }

    protected function renderTable($node, $cursor)
    {
        $table = $cursor->addTable('table');
        foreach ($node->content ?? [] as $r => $row) {
            foreach ($row->content ?? [] as $c => $cell) {
                $colspan = $cell->attrs->colspan ?? 1;
                if ($colspan > 1) {
                    array_splice($row->content, $c + 1, 0, array_fill(0, $colspan - 1, (object) [
                        'type' => 'table_cell',
                        'attrs' => (object) [
                            'colspan' => -1,
                        ],
                    ]));
                }
            }
        }
        foreach ($node->content ?? [] as $r => $row) {
            foreach ($row->content ?? [] as $c => $cell) {
                $rowspan = $cell->attrs->rowspan ?? 1;
                if ($rowspan > 1 && isset($node->content[$r + 1]->content)) {
                    array_splice($node->content[$r + 1]->content, $c, 0, [(object) [
                        'type' => 'table_cell',
                        'attrs' => (object) [
                            'rowspan' => -1,
                        ],
                    ]]);
                }
            }
        }
        foreach ($node->content ?? [] as $row) {
            $table->addRow();
            foreach ($row->content ?? [] as $cell) {
                $colspan = $cell->attrs->colspan ?? 1;
                $rowspan = $cell->attrs->rowspan ?? 1;
                if ($colspan === -1) {
                    continue;
                }
                $content = $cell->content ?? [];
                $text = array_shift($content);
                $this->renderNodes($text->content ?? [], $table->addCell(null, [
                    'gridSpan' => $colspan > 1 ? $colspan : null,
                    'vMerge' => $rowspan > 1 ? 'restart' : ($rowspan === -1 ? 'continue' : null),
                ]));
            }
        }
        $cursor->addTextBreak();
    }

    protected function renderText($node, $cursor, $textStyle = [])
    {
        if ($this->isLink($node)) {
            return $this->renderLink($node, $cursor);
        }

        $cursor->addText($node->text, $this->makeStyle($node, $textStyle));
    }

    protected function renderPageBreak($node, $cursor)
    {
        $cursor->addPageBreak();
    }

    protected function renderHardBreak($node, $cursor)
    {
        $cursor->addTextBreak();
    }

    protected function renderLink($node, $cursor)
    {
        $mark = $this->findMark($node, 'link');
        $cursor->addLink($mark->attrs->href ?? '#', $node->text, $this->makeStyle($node));
    }

    protected function renderFootnote($node, $cursor)
    {
        $nodes = $this->parseFootnoteHtml($node->attrs->{'data-content'} ?? null);
        $this->renderNodes($nodes ?? [], $cursor->addFootnote(), [
            'size' => 10,
        ]);
    }

    protected function renderTableOfContents($node, $cursor)
    {
        $labelNodes = $this->makeText($node->label);

        $this->renderNodes($labelNodes, $cursor->addTextRun([
            'spaceBefore' => $this->twip(10),
            'spaceAfter' => $this->twip(5),
        ]), [
            'size' => 14,
            'allCaps' => true,
        ]);

        $cursor->addTOC([
            'size' => 12,
        ], [
            'tabPos' => $this->twip(170),
        ]);
    }

    protected function renderOkTitle($node, $cursor)
    {
        $labelNodes = $this->makeText($node->label);
        $textNodes = $this->makeText($node->text);

        $this->renderNodes($labelNodes, $cursor->addTextRun([
            'spaceAfter' => $this->twip(3),
            'alignment' => Jc::CENTER,
        ]), [
            'allCaps' => true,
            'size' => 12,
        ]);
        $this->renderNodes($textNodes, $cursor->addTextRun([
            'spaceAfter' => $this->twip(3),
            'alignment' => Jc::CENTER,
        ]), [
            'size' => 28,
        ]);
    }

    protected function renderOkSummary($node, $cursor)
    {
        $nodes = $this->makeText($node->lines);

        $this->renderNodes($nodes, $cursor->addTextRun([
            'spaceAfter' => $this->twip(5),
            'alignment' => Jc::CENTER,
        ]));
        $cursor->addTextBreak();
    }

    protected function renderOkSuggestedCitationLong($node, $cursor)
    {
        $labelNodes = $this->makeText($node->label);
        $textNodes = $this->makeText($node->text);

        $this->renderNodes($labelNodes, $cursor->addTextRun([
            'alignment' => Jc::START,
            'spaceAfter' => $this->twip(2),
        ]), [
            'allCaps' => true,
            'size' => 12,
        ]);
        $this->renderNodes($textNodes, $cursor->addTextRun([
            'alignment' => Jc::START,
            'spaceAfter' => $this->twip(2),
        ]));
    }

    protected function renderOkSuggestedCitationShort($node, $cursor)
    {
        $nodes = $this->makeText($node->label.': '.$node->text);

        $this->renderNodes($nodes, $cursor->addTextRun([
            'spaceAfter' => $this->twip(10),
        ]));
    }

    protected function renderOkLegalText($node, $cursor)
    {
        $table = $cursor->addTable([
            'borderColor' => $this->colors['brown'],
            'cellMargin' => $this->twip(5),
        ]);
        $table->addRow();
        $cell = $table->addCell(null, [
            'width' => $this->twip(210 - $this->margin['left'] - $this->margin['right']),
            'unit' => TblWidth::TWIP,
            'bgColor' => $this->colors['brown'],
        ]);

        $nodes = collect($node->content ?? [])
            ->each(function ($node) {
                if ($node->type === 'heading') {
                    $node->type = 'paragraph';
                }
            })
            ->all();

        $this->renderNodes($nodes, $cell);
    }

    protected function parseFootnoteHtml($html)
    {
        $nodes = collect($this->editor->setContent($html)->getDocument()['content'] ?? [])
            ->map(fn ($node) => array_merge($node['content'], [['type' => 'hardBreak']]))
            ->flatten(1)
            ->slice(0, -1)
            ->all();

        return json_decode(json_encode($nodes));
    }

    protected function isParagraphWithNumber($node)
    {
        $first = $node->content[0] ?? null;
        if (! $first || $first->type !== 'text') {
            return false;
        }

        $mark = $this->findMark($first, ['btsSpan', 'bts_span']);
        if (! $mark || $mark->attrs->class !== 'paragraph-nr') {
            return false;
        }

        return true;
    }

    protected function isLink($node)
    {
        $mark = $this->findMark($node, 'link');
        if (! $mark) {
            return false;
        }

        return true;
    }

    protected function makeStyle($node, $textStyle = [])
    {
        $marks = collect($node->marks ?? [])->map->type;

        if ($marks->contains('bold')) {
            $textStyle['bold'] = true;
        }
        if ($marks->contains('italic')) {
            $textStyle['italic'] = true;
        }
        if ($marks->contains('underline')) {
            $textStyle['underline'] = Font::UNDERLINE_SINGLE;
        }
        if ($marks->contains('superscript')) {
            $textStyle['superScript'] = true;
        }
        if ($marks->contains('subscript')) {
            $textStyle['subScript'] = true;
        }
        if ($marks->contains('link')) {
            $textStyle['color'] = '0000FF';
            $textStyle['underline'] = Font::UNDERLINE_SINGLE;
        }
        if ($mark = $this->findMark($node, ['bts_span', 'btsSpan'])) {
            if ($mark->attrs->class === 'paragraph-nr') {
                $textStyle['size'] = 10;
            }
        }

        return $textStyle;
    }

    protected function findMark($node, $types)
    {
        $types = Arr::wrap($types);

        return collect($node->marks ?? [])->first(fn ($mark) => in_array($mark->type, $types));
    }

    protected function twip($value)
    {
        return Converter::cmToTwip($value / 10);
    }

    protected function point($value)
    {
        return Converter::cmToPoint($value / 10);
    }

    protected function lineHeight($value)
    {
        return 240 * ($value - 1);
    }

    protected function makeHeading($text, $level)
    {
        return (object) [
            'type' => 'heading',
            'attrs' => (object) ['level' => $level],
            'content' => $this->makeText($text),
        ];
    }

    protected function makeParagraph($text)
    {
        return (object) [
            'type' => 'paragraph',
            'content' => $this->makeText($text),
        ];
    }

    protected function makeText($lines)
    {
        $lines = Arr::wrap($lines);

        $nodes = [];
        foreach ($lines as $i => $line) {
            if ($i) {
                $nodes[] = (object) [
                    'type' => 'hardBreak',
                ];
            }
            $nodes[] = (object) [
                'type' => 'text',
                'text' => $line,
            ];
        }

        return $nodes;
    }
}
