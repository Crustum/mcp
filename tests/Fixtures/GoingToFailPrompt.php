<?php
declare(strict_types=1);

namespace Crustum\Mcp\Test\Fixtures;

use Crustum\Mcp\Request;
use Crustum\Mcp\Server\Prompt;

class GoingToFailPrompt extends Prompt
{
    protected string $description = 'This prompt is going to fail validation';

    public function handle(Request $request): void
    {
        $request->validateWith(mcpRequiredBooleanValidator('should_fail'));
    }
}
