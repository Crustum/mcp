<?php
declare(strict_types=1);

namespace Crustum\Mcp\Test\Fixtures;

use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\ResponseFactory;
use Crustum\Mcp\Server\Tool;
use Crustum\JsonSchema\Contracts\JsonSchema;

class StructuredContentWithMetaTool extends Tool
{
    protected string $description = 'This tool returns structured content with meta';

    public function handle(Request $request): ResponseFactory
    {
        return Response::structured([
            'result' => 'The operation completed successfully',
        ])->withMeta(['requestId' => 'abc123']);
    }

    #[\Override]
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
