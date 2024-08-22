<?php

namespace Textandbytes\Footnotes;

use Tiptap\Core\Node;
use Tiptap\Editor;
use Tiptap\Extensions;
use Tiptap\Marks;
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
        $node->content = $this->convertHtml($node->attrs->{'data-content'} ?? null);

        return ['footnote', HTML::mergeAttributes($this->options['HTMLAttributes'], $HTMLAttributes), 0];
    }

    protected function convertHtml($html)
    {
        $editor = new Editor([
            'extensions' => [
                new Extensions\StarterKit,
                new Marks\Underline,
                new Marks\Subscript,
                new Marks\Superscript,
                new Marks\Link,
            ],
        ]);

        $nodes = $editor->setContent($html)->getDocument()['content'][0]['content'];

        return json_decode(json_encode($nodes));
    }
}
