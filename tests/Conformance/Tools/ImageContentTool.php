<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Conformance\Tools;

use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Tool;
use Crustum\Mcp\Server\Conformance\Fixtures;

class ImageContentTool extends Tool
{
    protected string $name = 'test_image_content';

    protected string $description = 'Tests image content response';

    public function handle(Request $request): Response
    {
        return Response::image(Fixtures::IMAGE_BASE64, 'image/png');
    }
}
