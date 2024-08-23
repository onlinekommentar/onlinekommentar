<?php

namespace Textandbytes\Footnotes;

use Tiptap\Core\Node;

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
        $content = $node->attrs->{'data-content'} ?? null;

        return ['content' => '<footnote data-content="'.e($content).'">'.$content.'</footnote>'];
    }
}
