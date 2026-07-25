<?php
declare(strict_types=1);

namespace Crustum\Mcp\Test\Fixtures;

use Crustum\Mcp\Server;

class ServerWithDynamicTools extends Server
{
    public array $tools = [
    ];

    protected function boot(): void
    {
        $this->tools[] = SayHiTool::class;
        $this->tools[] = StreamingTool::class;
    }
}
