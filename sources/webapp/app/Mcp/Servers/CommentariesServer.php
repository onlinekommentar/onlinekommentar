<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\GetCommentary;
use App\Mcp\Tools\SearchCommentaries;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Onlinekommentar')]
#[Version('1.0.0')]
#[Instructions(<<<'MARKDOWN'
Onlinekommentar is a Swiss open access platform publishing peer reviewed legal commentaries on
Swiss legislation. Every commentary belongs to a legislative act and is written by named authors
and editors. Commentaries are published in German, English, French and Italian, and each language
is a separate commentary with its own text.

Use `search_commentaries` to find commentaries by full text query, optionally narrowed to a
language, a legislative act or a sort order. It returns a trimmed record per hit with a snippet of
the matching text. Use `get_commentary` with the `id` from a search hit to retrieve that
commentary's full text, legal text and suggested citations.

Search covers a single language at a time and defaults to English. Pass `language` as one of
`de`, `en`, `fr` or `it` when the user asks about a specific language edition, and prefer `de`
for Swiss legal questions, which is the platform's primary language and its largest corpus.

Always cite a commentary by the `url` and `suggested_citation_long` it returns rather than
paraphrasing without attribution.
MARKDOWN)]
class CommentariesServer extends Server
{
    protected array $tools = [
        SearchCommentaries::class,
        GetCommentary::class,
    ];
}
