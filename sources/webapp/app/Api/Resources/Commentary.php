<?php

namespace App\Api\Resources;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\QueryParameter;
use App\Api\State\CommentaryCollectionProvider;
use App\Api\State\CommentaryProvider;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    uriTemplate: '/commentaries',
    operations: [new GetCollection(provider: CommentaryCollectionProvider::class)],
    normalizationContext: ['groups' => ['commentary:list']],
    parameters: [
        'search' => new QueryParameter(description: 'Search by keyword'),
        'legislative_act' => new QueryParameter(description: 'Filter by legislative act identifier'),
        'language' => new QueryParameter(
            description: 'Filter by language code',
            schema: [
                'type' => 'string',
                'default' => 'en',
                'enum' => ['en', 'de', 'fr', 'it'],
            ]
        ),
        'sort' => new QueryParameter(
            description: 'Sort by date or title',
            schema: [
                'type' => 'string',
                'enum' => ['title', '-title', 'date', '-date'],
            ]
        ),
        'page' => new QueryParameter(description: 'Page number'),
    ]
)]
#[ApiResource(
    uriTemplate: '/commentaries/{id}',
    operations: [new Get(provider: CommentaryProvider::class)],
    normalizationContext: ['groups' => ['commentary:detail']],
)]
class Commentary
{
    public function __construct(
        #[Groups(['commentary:list', 'commentary:detail'])]
        public string $id,

        #[Groups(['commentary:list', 'commentary:detail'])]
        public string $title,

        #[Groups(['commentary:list', 'commentary:detail'])]
        public string $slug,

        #[Groups(['commentary:list', 'commentary:detail'])]
        public string $date,

        #[Groups(['commentary:list', 'commentary:detail'])]
        public string $language,

        #[Groups(['commentary:list', 'commentary:detail'])]
        public array $authors,

        #[Groups(['commentary:list', 'commentary:detail'])]
        public array $editors,

        #[Groups(['commentary:list', 'commentary:detail'])]
        public ?array $legislative_act,

        #[Groups(['commentary:detail'])]
        public ?string $suggested_citation_long = null,

        #[Groups(['commentary:detail'])]
        public ?string $suggested_citation_short = null,

        #[Groups(['commentary:detail'])]
        public ?string $content = null,

        #[Groups(['commentary:detail'])]
        public ?string $legal_text = null,

        #[Groups(['commentary:detail'])]
        public ?array $pdf_download_urls = null,

        #[Groups(['commentary:detail'])]
        public array $additional_document_urls = [],
    ) {}
}
