<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Conformance\Prompts;

use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Prompt;
use Crustum\Mcp\Server\Prompts\Argument;

class PromptWithEmbeddedResource extends Prompt
{
    protected string $name = 'test_prompt_with_embedded_resource';

    protected string $description = 'A prompt that includes an embedded resource';

    /**
     * @return array<int, \Crustum\Mcp\Response>
     */
    public function handle(Request $request): array
    {
        return [
            Response::resourceLink(
                uri: $request->get('resourceUri'),
                name: 'embedded-resource',
                description: 'Embedded resource content for testing.',
            ),
            Response::text('Please process the embedded resource above.'),
        ];
    }

    public function arguments(): array
    {
        return [
            new Argument('resourceUri', 'URI of the resource to embed', true),
        ];
    }
}
