<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Conformance\Tools;

use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Tool;

class ErrorHandlingTool extends Tool
{
    protected string $name = 'test_error_handling';

    protected string $description = 'Tests error response handling';

    public function handle(Request $request): Response
    {
        return Response::error('This tool intentionally returns an error for testing');
    }
}
