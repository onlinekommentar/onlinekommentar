<?php

namespace Textandbytes\Footnotes;

use Statamic\Support\Str;
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
        $content = Str::stripTags($node->attrs->{'data-content'} ?? null, ['p']);

        return ['content' => '<footnote data-content="'.e($content).'">'.$content.'</footnote>'];
    }
}
