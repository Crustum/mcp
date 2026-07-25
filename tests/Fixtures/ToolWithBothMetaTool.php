<?php
declare(strict_types=1);

namespace Crustum\Mcp\Test\Fixtures;

use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\ResponseFactory;
use Crustum\Mcp\Server\Tool;
use Crustum\JsonSchema\Contracts\JsonSchema;

class ToolWithBothMetaTool extends Tool
{
    protected string $description = 'This tool returns a response with both content-level and result-level meta';

    public function handle(Request $request): ResponseFactory
    {
        return Response::make([
            Response::text('First response')->withMeta(['content_index' => 1]),
            Response::text('Second response')->withMeta(['content_index' => 2]),
        ])->withMeta([
            'result_key' => 'result_value',
            'total_responses' => 2,
        ]);
    }

    #[\Override]
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
