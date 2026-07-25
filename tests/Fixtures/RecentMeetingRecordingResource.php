<?php
declare(strict_types=1);

namespace Crustum\Mcp\Test\Fixtures;

use Crustum\Mcp\Server\Resource;

class RecentMeetingRecordingResource extends Resource
{
    #[\Override]
    public function description(): string
    {
        return 'The most recent meeting recording';
    }

    public function handle(): string
    {
        return "This is a test resource.\0dummy-binary-data";
    }

    #[\Override]
    public function uri(): string
    {
        return 'file://resources/recent-meeting-recording.mp4';
    }

    #[\Override]
    public function mimeType(): string
    {
        return 'video/mp4';
    }
}
