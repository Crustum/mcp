<?php
declare(strict_types=1);

namespace Crustum\Mcp\Client\Schema;

use Cake\Utility\Hash;
use Crustum\Mcp\Enums\MetaKey;
use Crustum\Mcp\Exception\ClientException;
use Crustum\Mcp\Schema\Implementation;

/**
 * MCP server/discover handshake result.
 */
class DiscoverResult
{
    /**
     * Create a new discover result.
     *
     * @param array<int, string> $supportedVersions Supported protocol versions
     * @param array<string, mixed> $capabilities Server capabilities
     * @param \Crustum\Mcp\Schema\Implementation|null $serverInfo Server implementation metadata
     * @param string|null $instructions Optional server instructions
     */
    public function __construct(
        public array $supportedVersions,
        public array $capabilities,
        public ?Implementation $serverInfo = null,
        public ?string $instructions = null,
    ) {
    }

    /**
     * Create a discover result from a JSON-RPC result payload.
     *
     * @param array<string, mixed> $payload Discover result payload
     * @return self
     */
    public static function from(array $payload): self
    {
        $supportedVersions = Hash::get($payload, 'supportedVersions');
        $capabilities = Hash::get($payload, 'capabilities');
        $instructions = Hash::get($payload, 'instructions');
        $meta = Hash::get($payload, '_meta');
        $meta = is_array($meta) ? $meta : [];

        if (!is_array($supportedVersions) || !is_array($capabilities)) {
            throw new ClientException('Invalid discover response from server.');
        }

        return new self(
            supportedVersions: array_values(array_filter($supportedVersions, is_string(...))),
            capabilities: $capabilities,
            serverInfo: Implementation::from($meta[MetaKey::SERVER_INFO->value] ?? null),
            instructions: is_string($instructions) ? $instructions : null,
        );
    }
}
