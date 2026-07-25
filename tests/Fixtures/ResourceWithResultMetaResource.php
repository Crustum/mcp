<?php
declare(strict_types=1);

namespace Crustum\Mcp\Test\Fixtures;

use Crustum\Mcp\Response;
use Crustum\Mcp\ResponseFactory;
use Crustum\Mcp\Server\Resource;

class ResourceWithResultMetaResource extends Resource
{
    #[\Override]
    public function description(): string
    {
        return 'Resource with result-level meta';
    }

    public function handle(): ResponseFactory
    {
        return Response::make(
            Response::text('Resource content with result meta'),
        )->withMeta([
            'last_modified' => '2025-01-01',
            'version' => '1.0',
        ]);
    }

    #[\Override]
    public function uri(): string
    {
        return 'file://resources/with-result-meta.txt';
    }

    #[\Override]
    public function mimeType(): string
    {
        return 'text/plain';
    }
}
