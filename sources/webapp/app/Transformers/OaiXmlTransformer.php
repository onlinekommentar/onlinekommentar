<?php

namespace App\Transformers;

use XMLWriter;

class OaiXmlTransformer
{
    public static function writeRecord(XMLWriter $writer, array $data, string $metadataPrefix = 'oai_dc'): void
    {
        $writer->startElement('record');

        $writer->startElement('header');
        $writer->writeElement('identifier', $data['identifiers'][0]);
        $writer->writeElement('datestamp', $data['datestamp']);

        foreach ($data['setSpecs'] as $setSpec) {
            $writer->writeElement('setSpec', $setSpec);
        }

        $writer->endElement();

        $writer->startElement('metadata');

        if ($metadataPrefix === 'oai_dc') {
            static::writeDublinCore($writer, $data);
        } elseif ($metadataPrefix === 'oai_openaire') {
            static::writeOpenAire($writer, $data);
        }

        $writer->endElement();
        $writer->endElement();
    }

    public static function writeDublinCore(XMLWriter $writer, array $data): void
    {
        $writer->startElementNs('oai_dc', 'dc', 'http://www.openarchives.org/OAI/2.0/oai_dc/');
        $writer->writeAttribute('xmlns:dc', 'http://purl.org/dc/elements/1.1/');
        $writer->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $writer->writeAttribute('xsi:schemaLocation', 'http://www.openarchives.org/OAI/2.0/oai_dc/ http://www.openarchives.org/OAI/2.0/oai_dc.xsd');

        $writer->writeElementNs('dc', 'title', null, $data['title']);

        if ($data['subject']) {
            $writer->writeElementNs('dc', 'subject', null, $data['subject']);
        }

        if ($data['description']) {
            $writer->writeElementNs('dc', 'description', null, $data['description']);
        }

        $writer->writeElementNs('dc', 'publisher', null, $data['publisher']);

        foreach ($data['creators'] as $creator) {
            $writer->writeElementNs('dc', 'creator', null, $creator['name']);
        }

        foreach ($data['contributors'] as $contributor) {
            $writer->writeElementNs('dc', 'contributor', null, $contributor['name']);
        }

        if ($data['date']) {
            $writer->writeElementNs('dc', 'date', null, $data['date']);
        }

        $writer->writeElementNs('dc', 'type', null, $data['types']['dc']);

        foreach ($data['relations'] as $relation) {
            $writer->writeElementNs('dc', 'relation', null, $relation['url']);
        }

        foreach ($data['identifiers'] as $identifier) {
            $writer->writeElementNs('dc', 'identifier', null, $identifier);
        }

        $writer->writeElementNs('dc', 'rights', null, $data['rights']);
        $writer->writeElementNs('dc', 'coverage', null, $data['coverage']);

        $writer->writeElementNs('dc', 'language', null, $data['language']);

        $writer->endElement();
    }

    public static function writeOpenAire(XMLWriter $writer, array $data): void
    {
        $writer->startElementNs('oaire', 'resource', 'http://namespace.openaire.eu/schema/oaire/');
        $writer->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $writer->writeAttribute('xmlns:dc', 'http://purl.org/dc/elements/1.1/');
        $writer->writeAttribute('xmlns:dcterms', 'http://purl.org/dc/terms/');
        $writer->writeAttribute('xmlns:datacite', 'http://datacite.org/schema/kernel-4');
        $writer->writeAttribute('xsi:schemaLocation', 'http://namespace.openaire.eu/schema/oaire/ https://www.openaire.eu/schema/repo-lit/4.0/openaire.xsd');

        $writer->startElementNs('oaire', 'resourceType', null);
        $writer->writeAttribute('resourceTypeGeneral', $data['types']['openaireGeneral']);
        $writer->writeAttribute('uri', $data['types']['openaireUri']);
        $writer->text($data['types']['openaire']);
        $writer->endElement();

        $writer->startElementNs('datacite', 'titles', null);
        $writer->startElementNs('datacite', 'title', null);
        $writer->writeAttribute('xml:lang', $data['language']);
        $writer->text($data['title']);
        $writer->endElement();
        $writer->endElement();

        if (! empty($data['creators'])) {
            $writer->startElementNs('datacite', 'creators', null);
            foreach ($data['creators'] as $creator) {
                $writer->startElementNs('datacite', 'creator', null);
                $writer->writeAttribute('creatorType', $creator['type']);
                $writer->writeElementNs('datacite', 'creatorName', null, $creator['name']);
                $writer->endElement();
            }
            $writer->endElement();
        }

        if (! empty($data['contributors'])) {
            $writer->startElementNs('datacite', 'contributors', null);
            foreach ($data['contributors'] as $contributor) {
                $writer->startElementNs('datacite', 'contributor', null);
                $writer->writeAttribute('contributorType', $contributor['type']);
                $writer->writeElementNs('datacite', 'contributorName', null, $contributor['name']);
                $writer->endElement();
            }
            $writer->endElement();
        }

        $writer->writeElementNs('dc', 'language', null, $data['language']);

        $writer->writeElementNs('dc', 'publisher', null, $data['publisher']);

        $writer->startElementNs('datacite', 'dates', null);
        $writer->startElementNs('datacite', 'date', null);
        $writer->writeAttribute('dateType', 'Issued');
        $writer->text($data['date']);
        $writer->endElement();
        $writer->endElement();

        foreach ($data['identifiers'] as $identifier) {
            $writer->startElementNs('datacite', 'identifier', null);
            $writer->text($identifier);
            $writer->endElement();
        }

        foreach ($data['relations'] as $relation) {
            $writer->startElementNs('oaire', 'file', null);
            $writer->writeAttribute('objectType', 'fulltext');
            $writer->writeAttribute('mimeType', $relation['type']);
            $writer->text($relation['url']);
            $writer->endElement();
        }

        $writer->startElementNs('datacite', 'rights', null);
        $writer->text($data['rights']);
        $writer->endElement();

        if ($data['description']) {
            $writer->startElementNs('dc', 'description', null);
            $writer->writeAttribute('xml:lang', $data['language']);
            $writer->text($data['description']);
            $writer->endElement();
        }

        if ($data['subject']) {
            $writer->startElementNs('datacite', 'subjects', null);
            $writer->writeElementNs('datacite', 'subject', null, $data['subject']);
            $writer->endElement();
        }

        $writer->endElement();
    }

    public static function writeOaiHeader(XMLWriter $writer, array $data): void
    {
        $writer->startElement('header');
        $writer->writeElement('identifier', $data['identifier']);
        $writer->writeElement('datestamp', $data['datestamp']);
        foreach ($data['setSpecs'] as $setSpec) {
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
