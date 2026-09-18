<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Conformance\Resources;

use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Resource;
use Crustum\Mcp\Server\Conformance\Fixtures;

class StaticBinaryResource extends Resource
{
    protected string $name = 'static-binary';

    protected string $uri = 'test://static-binary';

    protected string $mimeType = 'image/png';

    protected string $description = 'A static binary resource (image) for testing';

    public function handle(): Response
    {
        return Response::blob(base64_decode(Fixtures::IMAGE_BASE64, true));
    }
}
