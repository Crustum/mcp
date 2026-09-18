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
        $serverInfo = Implementation::from(Hash::get($payload, 'serverInfo'));
        $instructions = Hash::get($payload, 'instructions');

        $version = is_string($protocolVersion) ? ProtocolVersion::tryFrom($protocolVersion) : null;

        if (!$version instanceof ProtocolVersion || !in_array($version->value, ProtocolVersion::initializeSupported(), true)) {
            throw new ClientException(sprintf(
                'The server chose protocol version [%s]. This client supports [%s].',
                is_string($protocolVersion) ? $protocolVersion : 'none',
                implode(', ', ProtocolVersion::initializeSupported()),
            ));
        }

        if (!is_array($capabilities) || !$serverInfo instanceof Implementation) {
            throw new ClientException('Invalid initialize response from server.');
        }

        return new self(
            protocolVersion: $protocolVersion,
            capabilities: $capabilities,
            serverInfo: $serverInfo,
            instructions: is_string($instructions) ? $instructions : null,
        );
    }
}
