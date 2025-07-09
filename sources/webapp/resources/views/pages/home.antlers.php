<?php

use Statamic\Facades\Entry;
use Statamic\Statamic;

// get the latest 3 commentaries that have valid content
$commentaries = Entry::query()
    ->where('collection', 'commentaries')
    ->where('locale', app()->getLocale())
    ->whereStatus('published')
    ->whereNotNull('content')
    ->limit(3)
    ->orderBy('date', 'desc')
    ->get()
    ->map(function ($commentary, $key) {
        return [
            'id' => $commentary['id'],
            'slug' => $commentary['slug'],
            'title' => $commentary['title'],
            'legal_domain' => Entry::query()
                ->where('collection', 'legal_domains')
                ->where('id', $commentary->value('legal_domain'))
                ->get()
                ->map(function ($legal_domain, $key) {
                    return [
                        'id' => $legal_domain['id'],
                        'label' => __($legal_domain['title']),
                    ];
                })
                ->first(),
            'assigned_authors' => $commentary['assigned_authors']->map(function ($author, $key) {
                return $author['name'];
            })->toArray(),
            'assigned_editors' => $commentary['assigned_editors']->map(function ($editor, $key) {
                return $editor['name'];
            })->toArray(),
        ];
    })
    ->toArray();

$corporates = Entry::query()
    ->where('collection', 'supporters')
    ->where('type', 'corporate')
    ->whereStatus('published')
    ->get()
    ->shuffle()
    ->map(function ($supporter, $key) {
        return [
            'id' => $supporter['id'],
            'title' => $supporter['title'],
            'link' => $supporter['link']->value(),
            'logo' => $supporter['logo']->url(),
            'width' => Statamic::modify($supporter['logo'])->areaWidth(5000)->fetch(),
        ];
    })
    ->toArray();
?>

<div class="max-w-3xl mx-auto mb-auto mt-8 p-6">
  <div class="mt-8 text-4xl leading-snug">
    {{ content }}
  </div>
  <a
    href="/{{ locale }}/ueber-onlinekommentar"
    class="inline-block mt-4 uppercase rounded-full border border-black text-xs px-4 py-2 font-medium tracking-widest">
    {{ trans:home_more_link }}
  </a>
</div>


<div class="mt-16 flex justify-between text-sm uppercase">
  <span>{{ trans:newest_comments }}</span>
  <div class="flex">
    <a href="{{ locale}}/kommentare"><span class="mr-2">{{ trans:all_comments }}</span>
      <svg class="inline-block mb-1" xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 11 11">
        <g id="Gruppe_51" data-name="Gruppe 51" transform="translate(10.5 -16.116) rotate(90)">
          <g id="Icon_feather-arrow-up" data-name="Icon feather-arrow-up" transform="translate(16.822)">
            <path id="Pfad_15" data-name="Pfad 15" d="M18,17.5V7.5" transform="translate(-13 -7.5)" fill="none" stroke="#000" stroke-linecap="round" stroke-linejoin="round" stroke-width="1"/>
            <path id="Pfad_16" data-name="Pfad 16" d="M7.5,13.178l5-5.678,5,5.678" transform="translate(-7.5 -7.5)" fill="none" stroke="#000" stroke-linecap="round" stroke-linejoin="round" stroke-width="1"/>
          </g>
        </g>
      </svg>
    </a>
  </div>
</div>

<div class="mt-2">
  <commentaries
    locale="{{ locale }}"
    :commentaries='<?= json_encode($commentaries, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>'
    :show-header-line="false"
    >
  </commentaries>
</div>

<div class="mt-12 mb-6">
  <h2 class="text-center mb-4 font-sans text-2xl tracking-wider uppercase">{{ trans:supporters_corporate }}:</h2>
  <logo-carousel
    :items='<?= json_encode($corporates, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>'>
  </logo-carousel>
  <p class="text-center">
    <a href="{{ site:url }}/unterstuetzerinnen"><span class="mr-2">{{ trans:supporters_member }}</span>
      <svg class="inline-block mb-1" xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 11 11">
        <g id="Gruppe_51" data-name="Gruppe 51" transform="translate(10.5 -16.116) rotate(90)">
          <g id="Icon_feather-arrow-up" data-name="Icon feather-arrow-up" transform="translate(16.822)">
            <path id="Pfad_15" data-name="Pfad 15" d="M18,17.5V7.5" transform="translate(-13 -7.5)" fill="none" stroke="#000" stroke-linecap="round" stroke-linejoin="round" stroke-width="1"/>
            <path id="Pfad_16" data-name="Pfad 16" d="M7.5,13.178l5-5.678,5,5.678" transform="translate(-7.5 -7.5)" fill="none" stroke="#000" stroke-linecap="round" stroke-linejoin="round" stroke-width="1"/>
          </g>
        </g>
      </svg>
    </a>
  </p>
</div>