<?php
declare(strict_types=1);

namespace Crustum\Mcp\Test\Fixtures;

use Crustum\Mcp\Server;

class ExampleServer extends Server
{
    public array $tools = [
        SayHiTool::class,
        StreamingTool::class,
    ];

    public array $resources = [
        LastLogLineResource::class,
        DailyPlanResource::class,
        RecentMeetingRecordingResource::class,
    ];

    #[\Override]
    protected function generateSessionId(): string
    {
        return 'overridden-' . uniqid();
    }
}
