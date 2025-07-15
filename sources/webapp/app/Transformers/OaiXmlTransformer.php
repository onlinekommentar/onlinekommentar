<?php

namespace App\Transformers;

use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Entry;
use XMLWriter;

class OaiXmlTransformer
{
    public static function writeRecord(XMLWriter $writer, EntryContract $entry, string $metadataPrefix = 'oai_dc'): void
    {
        $domain = parse_url(config('app.url'), PHP_URL_HOST);
        $identifier = "oai:{$domain}:commentary:{$entry->id}";
        $datestamp = $entry->date->format('Y-m-d');

        $setSpecs = [];
        if ($entry->get('legal_domain')) {
            $legalDomain = Entry::find($entry->get('legal_domain'));
            if ($legalDomain) {
                $setSpecs[] = 'legal_domain:'.$legalDomain->id;
            }
        }

        $writer->startElement('record');

        $writer->startElement('header');
        $writer->writeElement('identifier', $identifier);
        $writer->writeElement('datestamp', $datestamp);

        foreach ($setSpecs as $setSpec) {
            $writer->writeElement('setSpec', $setSpec);
        }

        $writer->endElement();

        $writer->startElement('metadata');

        if ($metadataPrefix === 'oai_dc') {
            static::writeDublinCore($writer, $entry);
        } elseif ($metadataPrefix === 'oai_openaire') {
            static::writeOpenAire($writer, $entry);
        }

        $writer->endElement();
        $writer->endElement();
    }

    public static function writeDublinCore(XMLWriter $writer, EntryContract $entry): void
    {
        $domain = parse_url(config('app.url'), PHP_URL_HOST);

        $writer->startElementNs('oai_dc', 'dc', 'http://www.openarchives.org/OAI/2.0/oai_dc/');
        $writer->writeAttribute('xmlns:dc', 'http://purl.org/dc/elements/1.1/');
        $writer->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $writer->writeAttribute('xsi:schemaLocation', 'http://www.openarchives.org/OAI/2.0/oai_dc/ http://www.openarchives.org/OAI/2.0/oai_dc.xsd');

        $writer->writeElementNs('dc', 'title', null, $entry->title);
        $writer->writeElementNs('dc', 'creator', null, 'Onlinekommentar');

        if ($entry->get('legal_domain')) {
            $legalDomain = Entry::find($entry->get('legal_domain'));
            if ($legalDomain) {
                $writer->writeElementNs('dc', 'subject', null, $legalDomain->title);
            }
        }

        if ($entry->suggested_citation_long) {
            $writer->writeElementNs('dc', 'description', null, $entry->suggested_citation_long);
        }

        $writer->writeElementNs('dc', 'publisher', null, 'Onlinekommentar');

        if ($entry->assigned_authors) {
            foreach ($entry->assigned_authors as $author) {
                $writer->writeElementNs('dc', 'contributor', null, $author->get('name'));
            }
        }

        if ($entry->assigned_editors) {
            foreach ($entry->assigned_editors as $editor) {
                $writer->writeElementNs('dc', 'contributor', null, $editor->get('name'));
            }
        }

        if ($entry->date) {
            $writer->writeElementNs('dc', 'date', null, $entry->date->format('Y-m-d'));
        }

        $writer->writeElementNs('dc', 'type', null, 'Text');

        $writer->writeElementNs('dc', 'format', null, 'text/html');
        $writer->writeElementNs('dc', 'format', null, 'application/pdf');
        $writer->writeElementNs('dc', 'format', null, 'application/json');

        $writer->writeElementNs('dc', 'source', null, $entry->absoluteUrl());
        $writer->writeElementNs('dc', 'source', null, route('commentaries.print', ['locale' => $entry->locale, 'commentarySlug' => $entry->slug]));
        $writer->writeElementNs('dc', 'source', null, route('api.commentaries.show', ['id' => $entry->id]));

        $identifier = 'oai:onlinekommentar.ch:'.$entry->id;
        $writer->writeElementNs('dc', 'identifier', null, $identifier);
        $writer->writeElementNs('dc', 'rights', null, '© '.date('Y').', Onlinekommentar.ch');
        $writer->writeElementNs('dc', 'coverage', null, $domain);

        $writer->writeElementNs('dc', 'language', null, $entry->locale ?? 'en');

        $writer->endElement();
    }

    public static function writeOpenAire(XMLWriter $writer, EntryContract $entry): void
    {
        $writer->startElementNs('oaire', 'resource', 'http://namespace.openaire.eu/schema/oaire/');
        $writer->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $writer->writeAttribute('xmlns:dc', 'http://purl.org/dc/elements/1.1/');
        $writer->writeAttribute('xmlns:dcterms', 'http://purl.org/dc/terms/');
        $writer->writeAttribute('xmlns:datacite', 'http://datacite.org/schema/kernel-4');
        $writer->writeAttribute('xsi:schemaLocation', 'http://namespace.openaire.eu/schema/oaire/ https://www.openaire.eu/schema/repo-lit/4.0/openaire.xsd');

        $writer->startElementNs('oaire', 'resourceType', null);
        $writer->writeAttribute('resourceTypeGeneral', 'literature');
        $writer->writeAttribute('uri', 'http://purl.org/coar/resource_type/c_93fc');
        $writer->text('commentary');
        $writer->endElement();

        $writer->startElementNs('datacite', 'titles', null);
        $writer->startElementNs('datacite', 'title', null);
        $writer->writeAttribute('xml:lang', $entry->locale ?? 'en');
        $writer->text($entry->title);
        $writer->endElement();
        $writer->endElement();

        $writer->startElementNs('datacite', 'creator', null);
        $writer->writeElementNs('datacite', 'creatorName', null, 'Onlinekommentar');
        $writer->endElement();

        if ($entry->assigned_authors || $entry->assigned_editors) {
            $writer->startElementNs('datacite', 'contributors', null);
            foreach ($entry->assigned_authors as $author) {
                $writer->startElementNs('datacite', 'contributor', null);
                $writer->writeAttribute('contributorType', 'Author');
                $writer->writeElementNs('datacite', 'contributorName', null, $author->get('name'));
                $writer->endElement();
            }
            foreach ($entry->assigned_editors as $editor) {
                $writer->startElementNs('datacite', 'contributor', null);
                $writer->writeAttribute('contributorType', 'Editor');
                $writer->writeElementNs('datacite', 'contributorName', null, $editor->get('name'));
                $writer->endElement();
            }
            $writer->endElement();
        }

        $writer->writeElementNs('dc', 'language', null, $entry->locale ?? 'en');

        $writer->writeElementNs('dc', 'publisher', null, 'Onlinekommentar');

        $writer->startElementNs('datacite', 'dates', null);
        $writer->startElementNs('datacite', 'date', null);
        $writer->writeAttribute('dateType', 'Issued');
        $writer->text($entry->date?->format('Y-m-d'));
        $writer->endElement();
        $writer->endElement();

        $writer->startElementNs('datacite', 'identifier', null);
        $writer->writeAttribute('identifierType', 'URL');
        $writer->text($entry->absoluteUrl());
        $writer->endElement();

        $writer->startElementNs('oaire', 'file', null);
        $writer->writeAttribute('objectType', 'fulltext');
        $writer->writeAttribute('mimeType', 'application/pdf');
        $writer->text(route('commentaries.print', ['locale' => $entry->locale, 'commentarySlug' => $entry->slug]));
        $writer->endElement();

        $writer->startElementNs('oaire', 'file', null);
        $writer->writeAttribute('objectType', 'fulltext');
        $writer->writeAttribute('mimeType', 'application/json');
        $writer->text(route('api.commentaries.show', ['id' => $entry->id]));
        $writer->endElement();

        $writer->startElementNs('datacite', 'rights', null);
        $writer->text('© '.date('Y').', Onlinekommentar.ch');
        $writer->endElement();

        if ($entry->suggested_citation_long) {
            $writer->startElementNs('dc', 'description', null);
            $writer->writeAttribute('xml:lang', $entry->locale ?? 'en');
            $writer->text($entry->suggested_citation_long);
            $writer->endElement();
        }

        if ($entry->get('legal_domain')) {
            $legalDomain = Entry::find($entry->get('legal_domain'));
            if ($legalDomain) {
                $writer->startElementNs('datacite', 'subjects', null);
                $writer->writeElementNs('datacite', 'subject', null, $legalDomain->title);
                $writer->endElement();
            }
        }

        $writer->endElement();
    }

    public static function writeOaiHeader(XMLWriter $writer, EntryContract $entry): void
    {
        $domain = parse_url(config('app.url'), PHP_URL_HOST);
        $identifier = "oai:{$domain}:commentary:{$entry->id}";
        $datestamp = $entry->date ? $entry->date->format('Y-m-d') : date('Y-m-d');

        $setSpecs = [];
        if ($entry->get('legal_domain')) {
            $legalDomain = Entry::find($entry->get('legal_domain'));
            if ($legalDomain) {
                $setSpecs[] = 'legal_domain:'.$legalDomain->id;
            }
        }

        $writer->startElement('header');
        $writer->writeElement('identifier', $identifier);
        $writer->writeElement('datestamp', $datestamp);
        foreach ($setSpecs as $setSpec) {
            $writer->writeElement('setSpec', $setSpec);
        }
        $writer->endElement();
    }

    public static function createOaiIdentifier(string $recordId): string
    {
        $domain = parse_url(config('app.url'), PHP_URL_HOST);

        return "oai:{$domain}:commentary:{$recordId}";
    }

    public static function writeResumptionToken(XMLWriter $writer, string $token, ?array $tokenData = null): void
    {
        $writer->startElement('resumptionToken');

        if ($tokenData) {
            if (isset($tokenData['completeListSize'])) {
                $writer->writeAttribute('completeListSize', (string) $tokenData['completeListSize']);
            }

            if (isset($tokenData['cursor'])) {
                $writer->writeAttribute('cursor', (string) $tokenData['cursor']);
            }

            $writer->writeAttribute('expirationDate', now()->addHour()->toISOString());
        }

        $writer->text($token);
        $writer->endElement();
    }
}
