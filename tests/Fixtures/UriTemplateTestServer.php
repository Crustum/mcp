<?php
declare(strict_types=1);

namespace Crustum\Mcp\Test\Fixtures;

use Crustum\Mcp\Server;

class UriTemplateTestServer extends Server
{
    protected array $resources = [
        UriTemplateSummaryResource::class,
        UriTemplateUserFileResource::class,
    ];
}
