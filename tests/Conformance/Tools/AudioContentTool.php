<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Conformance\Tools;

use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Tool;
use Crustum\Mcp\Server\Conformance\Fixtures;

class AudioContentTool extends Tool
{
    protected string $name = 'test_audio_content';

    protected string $description = 'Tests audio content response';

    public function handle(Request $request): Response
    {
        return Response::audio(Fixtures::AUDIO_BASE64, 'audio/wav');
    }
}
