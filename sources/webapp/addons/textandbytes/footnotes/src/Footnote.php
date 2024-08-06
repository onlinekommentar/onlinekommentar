<?php

namespace Textandbytes\Footnotes;

use Tiptap\Core\Node;
use Tiptap\Utils\HTML;

class Footnote extends Node
{
    public static $name = 'footnote';

    public function addOptions()
    {
        return [
            'HTMLAttributes' => [],
        ];
    }

    public function addAttributes()
    {
        return [
            'data-content' => [],
        ];
    }

    public function renderHTML($node, $HTMLAttributes = [])
    {
        $node->content = [(object) [
            'type' => 'text',
            'text' => $node->attrs->{'data-content'},
        ]];

        return ['footnote', HTML::mergeAttributes($this->options['HTMLAttributes'], $HTMLAttributes), 0];
    }
}
