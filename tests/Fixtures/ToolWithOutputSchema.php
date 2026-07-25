<?php
declare(strict_types=1);

namespace Crustum\Mcp\Test\Fixtures;

use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\ResponseFactory;
use Crustum\Mcp\Server\Tool;
use Crustum\JsonSchema\Contracts\JsonSchema;

class ToolWithOutputSchema extends Tool
{
    protected string $description = 'This tool returns user data with output schema';

    public function handle(Request $request): ResponseFactory
    {
        return Response::structured([
            'id' => 123,
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
    }

    #[\Override]
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    #[\Override]
    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('User ID')->required(),
            'name' => $schema->string()->description('User name')->required(),
            'email' => $schema->string()->description('User email'),
        ];
    }
}
