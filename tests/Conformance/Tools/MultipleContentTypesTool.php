<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Conformance\Tools;

use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Tool;
use Crustum\Mcp\Server\Conformance\Fixtures;

class MultipleContentTypesTool extends Tool
{
    protected string $name = 'test_multiple_content_types';

    protected string $description = 'Tests response with multiple content types';

    /**
     * @return array<int, \Crustum\Mcp\Response>
     */
    public function handle(Request $request): array
    {
        return [
            Response::text('Multiple content types test:'),
            Response::image(Fixtures::IMAGE_BASE64, 'image/png'),
            Response::resourceLink(
                uri: 'test://mixed-content-resource',
                name: 'mixed-content-resource',
                mimeType: 'application/json',
            ),
        ];
    }
}
