<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Conformance\Prompts;

use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Prompt;

class SimplePrompt extends Prompt
{
    protected string $name = 'test_simple_prompt';

    protected string $description = 'A simple prompt without arguments';

    public function handle(): Response
    {
        return Response::text('This is a simple prompt for testing.');
    }
}
