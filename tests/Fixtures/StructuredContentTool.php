<?php
declare(strict_types=1);

namespace Crustum\Mcp\Test\Fixtures;

use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\ResponseFactory;
use Crustum\Mcp\Server\Tool;
use Crustum\JsonSchema\Contracts\JsonSchema;

class StructuredContentTool extends Tool
{
    protected string $description = 'This tool returns structured content';

    public function handle(Request $request): ResponseFactory
    {
        return Response::structured([
            'temperature' => 22.5,
            'conditions' => 'Partly cloudy',
            'humidity' => 65,
        ]);
    }

    #[\Override]
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
