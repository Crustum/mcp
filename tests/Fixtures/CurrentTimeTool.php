<?php
declare(strict_types=1);

namespace Crustum\Mcp\Test\Fixtures;

use Crustum\Mcp\Server\Tool;

class CurrentTimeTool extends Tool
{
    protected string $description = 'This tool gets the current time';

    public function handle(Clock $clock): string
    {
        return 'The current time is ' . $clock->now();
    }
}
