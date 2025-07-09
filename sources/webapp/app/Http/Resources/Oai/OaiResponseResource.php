<?php

namespace App\Http\Resources\Oai;

use App\Transformers\OaiXmlTransformer;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use XMLWriter;

class OaiResponseResource implements Responsable
{
    protected mixed $resource;

    protected string $verb;

    protected array $responseData;

    protected ?string $error;

    protected ?string $errorMessage;

    public function __construct($resource, string $verb, array $responseData = [], ?string $error = null, ?string $errorMessage = null)
    {
        $this->resource = $resource;
        $this->verb = $verb;
        $this->responseData = $responseData;
        $this->error = $error;
        $this->errorMessage = $errorMessage;
    }

    public static function success(string $verb, array $data, Request $request): Response
    {
        $resource = new self($request, $verb, $data);

        return $resource->toResponse($request);
    }

    public static function error(string $errorCode, string $errorMessage, Request $request): Response
    {
        $resource = new self($request, '', [], $errorCode, $errorMessage);

        return $resource->toResponse($request);
    }

    public function toResponse($request): Response
    {
        $xml = $this->buildXmlResponse($request);

        return response($xml, 200, [
            'Content-Type' => 'text/xml; charset=UTF-8',
        ]);
    }

    protected function buildXmlResponse(Request $request): string
    {
        $writer = new XMLWriter;
        $writer->openMemory();
        $writer->setIndent(true);
        $writer->setIndentString('  ');

        $writer->startDocument('1.0', 'UTF-8');

        $writer->writePI('xml-stylesheet', 'type="text/xsl" href="'.url('/oai2.xsl').'"');

        $writer->startElement('OAI-PMH');
        $writer->writeAttribute('xmlns', 'http://www.openarchives.org/OAI/2.0/');
        $writer->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $writer->writeAttribute('xsi:schemaLocation', 'http://www.openarchives.org/OAI/2.0/ http://www.openarchives.org/OAI/2.0/OAI-PMH.xsd');

        $writer->writeElement('responseDate', now()->toISOString());

        $writer->startElement('request');
        foreach ($request->query() as $key => $value) {
            $writer->writeAttribute($key, $value);
        }
        $writer->text($request->fullUrl());
        $writer->endElement();

        if ($this->error) {
            $writer->startElement('error');
            $writer->writeAttribute('code', $this->error);
            $writer->text($this->errorMessage);
            $writer->endElement();
        } else {
            $this->buildVerbResponse($writer);
        }

        $writer->endElement();
        $writer->endDocument();

        return $writer->outputMemory();
    }

    protected function buildVerbResponse(XMLWriter $writer): void
    {
        match ($this->verb) {
            'Identify' => $this->buildIdentifyResponse($writer),
            'ListMetadataFormats' => $this->buildListMetadataFormatsResponse($writer),
            'ListSets' => $this->buildListSetsResponse($writer),
            'ListIdentifiers' => $this->buildListIdentifiersResponse($writer),
            'ListRecords' => $this->buildListRecordsResponse($writer),
            'GetRecord' => $this->buildGetRecordResponse($writer),
            default => null,
        };
    }

    protected function buildIdentifyResponse(XMLWriter $writer): void
    {
        $data = $this->responseData;

        $writer->startElement('Identify');
        $writer->writeElement('repositoryName', $data['repositoryName']);
        $writer->writeElement('baseURL', $data['baseURL']);
        $writer->writeElement('protocolVersion', $data['protocolVersion']);
        $writer->writeElement('adminEmail', $data['adminEmail']);
        $writer->writeElement('earliestDatestamp', $data['earliestDatestamp']);
        $writer->writeElement('deletedRecord', $data['deletedRecord']);
        $writer->writeElement('granularity', $data['granularity']);
        $writer->endElement();
    }

    protected function buildListMetadataFormatsResponse(XMLWriter $writer): void
    {
        $formats = $this->responseData['formats'] ?? [];

        $writer->startElement('ListMetadataFormats');

        foreach ($formats as $format) {
            $writer->startElement('metadataFormat');
            $writer->writeElement('metadataPrefix', $format['metadataPrefix']);
            $writer->writeElement('schema', $format['schema']);
            $writer->writeElement('metadataNamespace', $format['metadataNamespace']);
            $writer->endElement();
        }

        $writer->endElement();
    }

    protected function buildListSetsResponse(XMLWriter $writer): void
    {
        $sets = $this->responseData['sets'] ?? [];
        $resumptionToken = $this->responseData['resumptionToken'] ?? null;

        $writer->startElement('ListSets');

        foreach ($sets as $set) {
            $writer->startElement('set');
            $writer->writeElement('setSpec', $set['setSpec']);
            $writer->writeElement('setName', $set['setName']);
            if (isset($set['setDescription'])) {
                $writer->writeElement('setDescription', $set['setDescription']);
            }
            $writer->endElement();
        }

        if ($resumptionToken) {
            OaiXmlTransformer::writeResumptionToken($writer, $resumptionToken, $this->responseData['resumptionTokenData'] ?? null);
        }

        $writer->endElement();
    }

    protected function buildListIdentifiersResponse(XMLWriter $writer): void
    {
        $records = $this->responseData['records'] ?? [];
        $resumptionToken = $this->responseData['resumptionToken'] ?? null;

        $writer->startElement('ListIdentifiers');

        foreach ($records as $record) {
            OaiXmlTransformer::writeOaiHeader($writer, $record);
        }

        if ($resumptionToken) {
            OaiXmlTransformer::writeResumptionToken($writer, $resumptionToken, $this->responseData['resumptionTokenData'] ?? null);
        }

        $writer->endElement();
    }

    protected function buildListRecordsResponse(XMLWriter $writer): void
    {
        $records = $this->responseData['records'] ?? [];
        $metadataPrefix = $this->responseData['metadataPrefix'] ?? 'oai_dc';
        $resumptionToken = $this->responseData['resumptionToken'] ?? null;

        $writer->startElement('ListRecords');

        foreach ($records as $record) {
            RecordResource::make($record)
                ->withMetadataPrefix($metadataPrefix)
                ->writeXml($writer);
        }

        if ($resumptionToken) {
            OaiXmlTransformer::writeResumptionToken($writer, $resumptionToken, $this->responseData['resumptionTokenData'] ?? null);
        }

        $writer->endElement();
    }

    protected function buildGetRecordResponse(XMLWriter $writer): void
    {
        $record = $this->responseData['record'];
        $metadataPrefix = $this->responseData['metadataPrefix'] ?? 'oai_dc';

        $writer->startElement('GetRecord');
        RecordResource::make($record)
            ->withMetadataPrefix($metadataPrefix)
            ->writeXml($writer);
        $writer->endElement();
    }

    protected function createOaiIdentifier(string $recordId): string
    {
        return OaiXmlTransformer::createOaiIdentifier($recordId);
    }

    protected function writeResumptionToken(XMLWriter $writer, string $token, ?array $tokenData = null): void
    {
        OaiXmlTransformer::writeResumptionToken($writer, $token, $tokenData);
    }
}
