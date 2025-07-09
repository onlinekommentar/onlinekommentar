<?php

namespace App\Http\Resources\Oai;

use App\Transformers\OaiXmlTransformer;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Response;
use XMLWriter;

class DublinCoreResource implements Responsable
{
    protected mixed $resource;

    public function __construct($resource)
    {
        $this->resource = $resource;
    }

    public static function make($resource): self
    {
        return new static($resource);
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
        OaiXmlTransformer::writeDublinCore($writer, $this->resource);
    }
}
