<?php
declare(strict_types=1);

namespace Crustum\Mcp\Client\OAuth;

/**
 * OAuth authorization server discovery result.
 */
class DiscoveryResult
{
    /**
     * Create a discovery result.
     *
     * @param \Crustum\Mcp\Client\OAuth\AuthServerMetadata $server Authorization server metadata
     * @param array<int, string> $scopesSupported Supported OAuth scopes
     */
    public function __construct(
        public AuthServerMetadata $server,
        public array $scopesSupported = [],
    ) {
    }
}
