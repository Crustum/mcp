<?php
declare(strict_types=1);

namespace Crustum\Mcp\Test\Fixtures;

use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Contracts\HasUriTemplate;
use Crustum\Mcp\Server\Resource;
use Crustum\Mcp\Support\UriTemplate;

class ExampleResourceTemplate extends Resource implements HasUriTemplate
{
    protected string $description = 'Example resource template for testing';

    protected string $mimeType = 'text/plain';

    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('file://example/{id}');
    }

    public function handle(Request $request): Response
    {
        $id = $request->get('id');

        return Response::text("Example resource: {$id}");
    }
}
