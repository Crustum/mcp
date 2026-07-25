<?php
declare(strict_types=1);

namespace Crustum\Mcp\Test\Fixtures;

use Crustum\Mcp\Server\Resource;

class DailyPlanResource extends Resource
{
    #[\Override]
    public function description(): string
    {
        return 'The plan for the day';
    }

    public function handle(): string
    {
        // Dummy markdown content representing the daily plan.
        return "# Daily Plan\n\n- [ ] Task 1\n- [ ] Task 2\n- [ ] Task 3";
    }

    #[\Override]
    public function uri(): string
    {
        return 'file://resources/daily-plan.md';
    }

    #[\Override]
    public function mimeType(): string
    {
        return 'text/markdown';
    }
}
