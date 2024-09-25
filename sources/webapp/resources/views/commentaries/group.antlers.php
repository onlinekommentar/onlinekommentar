<?php

use Statamic\Facades\Entry;
use Statamic\Statamic;

$tree = Statamic::tag('nav:collection:commentaries')
    ->params([
        'from' => $uri,
        'site' => $site->handle(),
    ])
    ->fetch();

$breadcrumbs = Statamic::tag('nav:breadcrumbs')
    ->fetch();

$commentaries = collect($tree)
    ->flatMap(function ($commentary) {
        return $commentary['blueprint']->value()->handle() !== 'commentary' ? $commentary['children'] : [$commentary];
    })
    ->map(function ($commentary, $key) {
        return [
            'id' => $commentary['id']->value(),
            'slug' => $commentary['slug']->value(),
            'title' => $commentary['title']->value(),
            'legal_domain' => Entry::query()
                ->where('collection', 'legal_domains')
                ->where('id', $commentary['legal_domain']->raw())
                ->get()
                ->map(function ($legal_domain, $key) {
                    return [
                        'id' => $legal_domain['id'],
                        'label' => __($legal_domain['title']),
                    ];
                })
                ->first(),
            'assigned_authors' => $commentary['assigned_authors']->value()->get()->map(function ($author, $key) {
                return $author['name'];
            })->toArray(),
            'assigned_editors' => $commentary['assigned_editors']->value()->get()->map(function ($editor, $key) {
                return $editor['name'];
            })->toArray(),
        ];
    })
    ->toArray();
?>

<commentaries
  locale="{{ locale }}"
  :show-header-line="false"
  :show-title-line="true"
  title="{{ title }}"
  :commentaries='<?= json_encode($commentaries, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>'
  :legal-domains='<?= json_encode([]) ?>'>
</commentaries>