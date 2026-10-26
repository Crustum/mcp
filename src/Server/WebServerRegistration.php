<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server;

/**
 * Registered MCP web server metadata.
 */
final readonly class WebServerRegistration
{
    /**
     * @param string $uri Registered route URI
     * @param class-string<\Crustum\Mcp\Server> $serverClass Server class name
     * @param array<int, string> $middleware Additional route middleware aliases
     */
    public function __construct(
        public string $uri,
        public string $serverClass,
        public array $middleware = [],
    ) {
    }
}
