<?php

namespace Textandbytes\Converter;

use DOMXPath;
use Masterminds\HTML5;

class TocBuilder
{
    public function build(string $html): string
    {
        $headings = $this->collectHeadings($html);

        if (count($headings) === 0) {
            return '';
        }

        $topLevel = min(array_column($headings, 'level'));

        $headings = array_map(fn ($heading) => array_merge($heading, [
            'depth' => $heading['level'] - $topLevel + 1,
        ]), $headings);

        $index = 0;

        return $this->renderList($headings, 1, $index);
    }

    protected function collectHeadings(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        $query = (new DOMXPath((new HTML5)->loadHTML($html)))->query(sprintf(
            '//*[%s]',
            implode(' or ', array_map(fn ($level) => sprintf('local-name() = "h%d"', $level), range(1, 6)))
        ));

        if (! $query) {
            return [];
        }

        $headings = [];

        foreach ($query as $node) {
            if (! $node->hasAttribute('id')) {
                continue;
            }

            $headings[] = [
                'level' => (int) substr($node->localName, 1),
                'id' => $node->getAttribute('id'),
                'label' => $node->getAttribute('title') ?: $node->textContent,
            ];
        }

        return $headings;
    }

    protected function renderList(array $headings, int $depth, int &$index): string
    {
        $html = '<ul>';

        while ($index < count($headings)) {
            if ($headings[$index]['depth'] < $depth) {
                break;
            }

            if ($headings[$index]['depth'] > $depth) {
                $html .= '<li><span></span>'.$this->renderList($headings, $depth + 1, $index).'</li>';

                continue;
            }

            $heading = $headings[$index];
            $index++;

            $html .= sprintf(
                '<li><a href="#%s">%s</a>',
                $this->escape($heading['id']),
                $this->escape($heading['label'])
            );

            if ($index < count($headings) && $headings[$index]['depth'] > $depth) {
                $html .= $this->renderList($headings, $depth + 1, $index);
            }

            $html .= '</li>';
        }

        return $html.'</ul>';
    }

    protected function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
