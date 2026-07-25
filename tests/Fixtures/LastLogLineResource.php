<?php
declare(strict_types=1);

namespace Crustum\Mcp\Test\Fixtures;

use Crustum\Mcp\Server\Resource;

class LastLogLineResource extends Resource
{
    #[\Override]
    public function description(): string
    {
        return 'The last line of the log file';
    }

    public function handle(): string
    {
        return '2025-07-02 12:00:00 Error: Something went wrong.';
    }
}
