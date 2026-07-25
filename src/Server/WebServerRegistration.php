<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server;

/**
 * Registered MCP web server metadata.
 */
final class WebServerRegistration
{
    /**
     * @param string $uri Registered route URI
     * @param class-string<\Crustum\Mcp\Server> $serverClass Server class name
     * @param array<int, string> $middleware Additional route middleware aliases
     */
    public function __construct(
        public readonly string $uri,
        public readonly string $serverClass,
        public readonly array $middleware = [],
    ) {
    }
}
