<?php
declare(strict_types=1);

namespace Crustum\Mcp\Client\Schema;

use Cake\Utility\Hash;
use Crustum\Mcp\Enums\ProtocolVersion;
use Crustum\Mcp\Exception\ClientException;
use Crustum\Mcp\Schema\Implementation;

/**
 * MCP initialize handshake result.
 */
class InitializeResult
{
    /**
     * Create a new initialize result.
     *
     * @param string $protocolVersion Negotiated protocol version
     * @param array<string, mixed> $capabilities Server capabilities
     * @param \Crustum\Mcp\Schema\Implementation $serverInfo Server implementation metadata
     * @param string|null $instructions Optional server instructions
     */
    public function __construct(
        public string $protocolVersion,
        public array $capabilities,
        public Implementation $serverInfo,
        public ?string $instructions = null,
    ) {
    }

    /**
     * Create an initialize result from a JSON-RPC result payload.
     *
     * @param array<string, mixed> $payload Initialize result payload
     * @return self
     */
    public static function from(array $payload): self
    {
        $protocolVersion = Hash::get($payload, 'protocolVersion');
        $capabilities = Hash::get($payload, 'capabilities');
        $serverInfo = Hash::get($payload, 'serverInfo');
        $serverName = Hash::get($serverInfo, 'name');
        $serverVersion = Hash::get($serverInfo, 'version');
        $instructions = Hash::get($payload, 'instructions');

        if (!is_string($protocolVersion) || !in_array($protocolVersion, ProtocolVersion::clientSupported(), true)) {
            throw new ClientException('The server negotiated an unsupported protocol version.');
        }

        if (
            !is_array($capabilities)
            || !is_array($serverInfo)
            || !is_string($serverName)
            || !is_string($serverVersion)
        ) {
            throw new ClientException('Invalid initialize response from server.');
        }

        return new self(
            protocolVersion: $protocolVersion,
            capabilities: $capabilities,
            serverInfo: Implementation::from($serverInfo),
            instructions: is_string($instructions) ? $instructions : null,
        );
    }
}
