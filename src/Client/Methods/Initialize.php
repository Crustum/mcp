<?php
declare(strict_types=1);

namespace Crustum\Mcp\Client\Methods;

use Crustum\Mcp\Client\Contracts\Method;
use Crustum\Mcp\Client\Protocol;
use Crustum\Mcp\Client\Schema\InitializeResult;
use Crustum\Mcp\Enums\ProtocolVersion;
use Crustum\Mcp\Schema\Implementation;

/**
 * MCP initialize JSON-RPC method.
 *
 * @implements \Crustum\Mcp\Client\Contracts\Method<\Crustum\Mcp\Client\Schema\InitializeResult>
 */
class Initialize implements Method
{
    /**
     * Create a new initialize method.
     *
     * @param \Crustum\Mcp\Schema\Implementation $clientInfo Client implementation metadata
     * @param \Crustum\Mcp\Enums\ProtocolVersion $protocolVersion Protocol version to offer
     */
    public function __construct(
        protected Implementation $clientInfo,
        protected ProtocolVersion $protocolVersion = ProtocolVersion::V2025_11_25,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function method(): string
    {
        return 'initialize';
    }

    /**
     * @inheritDoc
     */
    public function params(): array
    {
        return [
            'protocolVersion' => $this->protocolVersion->value,
            'capabilities' => (object)[],
            'clientInfo' => $this->clientInfo->toArray(),
        ];
    }

    /**
     * @inheritDoc
     */
    public function handle(Protocol $protocol): InitializeResult
    {
        return InitializeResult::from($protocol->dispatch($this));
    }
}
