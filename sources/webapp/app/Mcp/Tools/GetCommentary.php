<?php

namespace App\Mcp\Tools;

use App\Http\Resources\CommentaryResource;
use App\Services\CommentaryQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('get_commentary')]
#[Title('Get a commentary')]
#[Description('Retrieve one Onlinekommentar commentary in full by its id, including its content, the legal text it comments on and its suggested citations. Ids come from search_commentaries.')]
class GetCommentary extends Tool
{
    public function handle(Request $request): Response
    {
        ['id' => $id] = $request->validate([
            'id' => ['required', 'string'],
        ]);

        $entry = CommentaryQuery::find($id);

        if (! $entry) {
            return Response::error("No published commentary found with the id [{$id}].");
        }

        $commentary = CommentaryResource::make($entry)->detailed()->resolve(request());

        return Response::text(json_encode($commentary, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()
                ->required()
                ->description('The commentary\'s id, as returned in a search_commentaries result.'),
        ];
    }
}
