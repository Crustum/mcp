<?php
declare(strict_types=1);

namespace Crustum\Mcp\Test\Fixtures;

use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\ResponseFactory;
use Crustum\Mcp\Server\Tool;
use Crustum\JsonSchema\Contracts\JsonSchema;

class ResponseFactoryWithStructuredContentTool extends Tool
{
    protected string $description = 'This tool returns a ResponseFactory with structured content';

    public function handle(Request $request): ResponseFactory
    {
        return Response::make([
            Response::text('Processing complete with status: success'),
        ])->withStructuredContent([
            'status' => 'success',
            'code' => 200,
        ]);
    }

    #[\Override]
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
