<?php
declare(strict_types=1);

namespace Crustum\Mcp\Client;

use Crustum\Mcp\Client\Schema\DiscoverResult;
use Crustum\Mcp\Client\Schema\InitializeResult;
use Crustum\Mcp\Enums\ProtocolVersion;
use Crustum\Mcp\Schema\Implementation;

/**
 * A negotiated MCP connection with its settled protocol version and result.
 */
class NegotiatedConnection
{
    /**
     * Create a new negotiated connection.
     *
     * @param \Crustum\Mcp\Enums\ProtocolVersion $protocolVersion Settled protocol version
     * @param \Crustum\Mcp\Client\Schema\DiscoverResult|\Crustum\Mcp\Client\Schema\InitializeResult $result Handshake result
     */
    public function __construct(
        public ProtocolVersion $protocolVersion,
        public DiscoverResult|InitializeResult $result,
    ) {
    }

    /**
     * Get the discover result when this connection used discovery.
     *
     * @return \Crustum\Mcp\Client\Schema\DiscoverResult|null
     */
    public function discoverResult(): ?DiscoverResult
    {
        return $this->result instanceof DiscoverResult ? $this->result : null;
    }

    /**
     * Get the initialize result when this connection used the handshake.
     *
     * @return \Crustum\Mcp\Client\Schema\InitializeResult|null
     */
    public function initializeResult(): ?InitializeResult
    {
        return $this->result instanceof InitializeResult ? $this->result : null;
    }

    /**
     * Get the negotiated server capabilities.
     *
     * @return array<string, mixed>
     */
    public function capabilities(): array
    {
        return $this->result->capabilities;
    }

    /**
     * Get the negotiated server implementation.
     *
     * @return \Crustum\Mcp\Schema\Implementation|null
     */
    public function serverInfo(): ?Implementation
    {
        return $this->result->serverInfo;
    }

    /**
     * Get the negotiated server instructions.
     *
     * @return string|null
     */
    public function instructions(): ?string
    {
        return $this->result->instructions;
    }
}
