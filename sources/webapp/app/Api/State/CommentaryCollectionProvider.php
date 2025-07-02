<?php

namespace App\Api\State;

use ApiPlatform\Laravel\Eloquent\Paginator;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Api\Services\CommentaryService;

class CommentaryCollectionProvider implements ProviderInterface
{
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        $request = $context['request'];

        return new Paginator(CommentaryService::all(
            search: $request->search,
            legislativeAct: $request->legislative_act,
            language: $request->language,
            sort: $request->sort,
            page: $request->page,
        ));
    }
}
