<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Conformance\Tools;

use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Tool;

class EmbeddedResourceTool extends Tool
{
    protected string $name = 'test_embedded_resource';

    protected string $description = 'Tests embedded resource content response';

    public function handle(Request $request): Response
    {
        return Response::resourceLink(
            uri: 'test://embedded-resource',
            name: 'embedded-resource',
            mimeType: 'text/plain',
            description: 'This is an embedded resource content.',
        );
    }
}
