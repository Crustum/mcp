<?php
declare(strict_types=1);

namespace Crustum\Mcp\Test\Fixtures;

use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\ResponseFactory;
use Crustum\Mcp\Server\Tool;
use Crustum\JsonSchema\Contracts\JsonSchema;

class ToolWithResultMetaTool extends Tool
{
    protected string $description = 'This tool returns a response with result-level meta';

    public function handle(Request $request): ResponseFactory
    {
        return Response::make(
            Response::text('Tool response with result meta'),
        )->withMeta([
            'session_id' => 50,
        ]);
    }

    #[\Override]
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
