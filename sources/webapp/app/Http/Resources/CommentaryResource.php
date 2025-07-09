<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Statamic\Facades\Entry as EntryFacade;

class CommentaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $entry = $this->resource;

        return [
            'id' => $entry->id,
            'title' => $entry->title,
            'slug' => $entry->slug,
            'date' => $entry->date()?->format('Y-m-d'),
            'language' => $entry->locale,
            'authors' => $this->transformUsers($entry->assigned_authors)->all(),
            'editors' => $this->transformUsers($entry->assigned_editors)->all(),
            'legislative_act' => $this->transformLegalDomain($entry->value('legal_domain')),
            $this->mergeWhen($request->routeIs('api.json.commentaries.show'), [
                'suggested_citation_long' => $entry->suggested_citation_long,
                'suggested_citation_short' => $entry->suggested_citation_short,
                'content' => $entry->content,
                'legal_text' => $entry->legal_text,
                'pdf_download_urls' => [
                    route('commentaries.print', ['locale' => $entry->locale, 'commentarySlug' => $entry->slug, 'text' => 'md']),
                    route('commentaries.print', ['locale' => $entry->locale, 'commentarySlug' => $entry->slug, 'text' => 'lg']),
                ],
                'additional_document_urls' => $this->transformAssets($entry->additional_documents)->all(),
            ]),
        ];
    }

    protected function transformUsers($users): Collection
    {
        if (! $users) {
            return collect();
        }

        return collect($users)
            ->map(fn ($user) => [
                'id' => $user->id(),
                'name' => $user->get('name'),
            ]);
    }

    protected function transformAssets($assets): Collection
    {
        if (! $assets) {
            return collect();
        }

        return collect($assets)
            ->map(fn ($asset) => $asset->absoluteUrl());
    }

    protected function transformLegalDomain($legalDomain): ?array
    {
        if (! $legalDomain) {
            return null;
        }

        $legalDomain = EntryFacade::find($legalDomain);
        if (! $legalDomain) {
            return null;
        }

        return [
            'id' => $legalDomain->id,
            'title' => $legalDomain->title,
        ];
    }
}
