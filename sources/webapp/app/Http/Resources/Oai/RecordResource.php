<?php

namespace App\Http\Resources\Oai;

use App\Transformers\OaiXmlTransformer;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Response;
use Statamic\Facades\Entry;
use XMLWriter;

class RecordResource implements Responsable
{
    protected mixed $resource;

    protected string $metadataPrefix = 'oai_dc';

    public function __construct($resource)
    {
        $this->resource = $resource;
    }

    public static function make($resource): self
    {
        return new static($resource);
    }

    public function withMetadataPrefix(string $prefix): self
    {
        $this->metadataPrefix = $prefix;

        return $this;
    }

    public function toArray(): array
    {
        $entry = $this->resource;
        $domain = parse_url(config('app.url'), PHP_URL_HOST);

        $setSpecs = [];
        if ($entry->get('legal_domain')) {
            $legalDomain = Entry::find($entry->get('legal_domain'));
            if ($legalDomain) {
                $setSpecs[] = 'legal_domain:'.$legalDomain->id;
            }
        }

        $creators = [];
        if ($entry->assigned_authors) {
            foreach ($entry->assigned_authors as $author) {
                $creators[] = [
                    'name' => $author->get('name'),
                    'type' => 'Author',
                ];
            }
        }

        $contributors = [];
        if ($entry->assigned_editors) {
            foreach ($entry->assigned_editors as $editor) {
                $contributors[] = [
                    'name' => $editor->get('name'),
                    'type' => 'Editor',
                ];
            }
        }

        $identifiers = array_filter([
            "oai:{$domain}:commentary:{$entry->id}",
            $entry->doi,
            $entry->absoluteUrl(),
        ]);

        $relations = [
            [
                'type' => 'application/pdf',
                'url' => route('commentaries.print', ['locale' => $entry->locale, 'commentarySlug' => $entry->slug]),
            ],
            [
                'type' => 'application/json',
                'url' => route('api.commentaries.show', ['id' => $entry->id]),
            ],
        ];

        $subject = null;
        if ($entry->get('legal_domain')) {
            $legalDomain = Entry::find($entry->get('legal_domain'));
            if ($legalDomain) {
                $subject = $legalDomain->title;
            }
        }

        return [
            'title' => $entry->title,
            'language' => $entry->locale ?? 'en',
            'date' => $entry->date ? $entry->date->format('Y-m-d') : null,
            'publisher' => 'Onlinekommentar',
            'creators' => $creators,
            'contributors' => $contributors,
            'rights' => 'https://creativecommons.org/licenses/by/4.0/',
            'types' => [
                'dc' => 'commentary',
                'openaire' => 'commentary',
                'openaireGeneral' => 'literature',
                'openaireUri' => 'http://purl.org/coar/resource_type/c_93fc',
            ],
            'description' => $entry->suggested_citation_long,
            'subject' => $subject,
            'identifiers' => $identifiers,
            'relations' => $relations,
            'coverage' => 'Law',
            'setSpecs' => $setSpecs,
            'datestamp' => $entry->date ? $entry->date->format('Y-m-d') : date('Y-m-d'),
        ];
    }

    public function toResponse($request): Response
    {
        $writer = new XMLWriter;
        $writer->openMemory();
        $writer->setIndent(true);
        $writer->setIndentString('  ');

        $this->writeXml($writer);

        return response($writer->outputMemory(), 200, [
            'Content-Type' => 'text/xml; charset=UTF-8',
        ]);
    }

    public function writeXml(XMLWriter $writer): void
    {
        OaiXmlTransformer::writeRecord($writer, $this->toArray(), $this->metadataPrefix);
    }
}
