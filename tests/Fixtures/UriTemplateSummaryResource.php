<?php
declare(strict_types=1);

namespace Crustum\Mcp\Test\Fixtures;

use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Contracts\HasUriTemplate;
use Crustum\Mcp\Server\Resource;
use Crustum\Mcp\Support\UriTemplate;

class UriTemplateSummaryResource extends Resource implements HasUriTemplate
{
    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('file://summary/{id}');
    }

    public function handle(Request $request): Response
    {
        $request->validateWith(mcpRequiredIdValidator());

        return Response::json([
            'id' => $request->get('id'),
            'uri' => $request->uri(),
        ]);
    }
}
