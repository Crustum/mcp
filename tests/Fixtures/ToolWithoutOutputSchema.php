<?php
declare(strict_types=1);

namespace Crustum\Mcp\Test\Fixtures;

use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Tool;
use Crustum\JsonSchema\Contracts\JsonSchema;

class ToolWithoutOutputSchema extends Tool
{
    protected string $description = 'This tool does not define an output schema';

    public function handle(Request $request): Response
    {
        return Response::text('Simple text response without schema');
    }

    #[\Override]
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
