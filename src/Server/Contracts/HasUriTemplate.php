<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Contracts;

use Crustum\Mcp\Support\UriTemplate;

/**
 * Contract for MCP resources that expose a URI template.
 */
interface HasUriTemplate
{
    /**
     * Get the URI pattern for the resource template.
     *
     * @return \Crustum\Mcp\Support\UriTemplate
     */
    public function uriTemplate(): UriTemplate;
}
