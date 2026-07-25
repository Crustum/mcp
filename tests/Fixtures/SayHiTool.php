<?php
declare(strict_types=1);

namespace Crustum\Mcp\Test\Fixtures;

use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Tool;
use Crustum\JsonSchema\Contracts\JsonSchema;

class SayHiTool extends Tool
{
    protected string $description = 'This tool says hello to a person';

    public function handle(Request $request): Response
    {
        $request->validateWith(mcpRequiredNameValidator());

        $name = $request->get('name');

        return Response::text('Hello, ' . $name . '!');
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
