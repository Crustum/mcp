<?php
declare(strict_types=1);

namespace Crustum\Mcp\Test\Fixtures;

use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Tool;
use Crustum\JsonSchema\Contracts\JsonSchema;

class SayHiWithMetaTool extends Tool
{
    protected string $description = 'This tool says hello to a person with metadata';

    protected ?array $meta = [
        'requestId' => 'abc-123',
        'source' => 'tests/fixtures',
    ];

    public function handle(Request $request): Response
    {
        $request->validateWith(mcpRequiredNameValidator());

        $name = $request->get('name');

        return Response::text('Hello, ' . $name . '!')->withMeta([
            'test' => 'metadata',
        ]);
    }

    #[\Override]
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()
                ->description('The name of the person to greet')
                ->required(),
        ];
    }
}
