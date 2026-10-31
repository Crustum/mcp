<?php
declare(strict_types=1);

namespace Crustum\Mcp\Client\Contracts;

use Crustum\Mcp\Enum\ProtocolVersion;

/**
 * Contract for transports that need to know the active protocol era.
 */
interface UsesProtocol
{
    /**
     * Configure the transport for the given protocol version.
     *
     * @param \Crustum\Mcp\Enum\ProtocolVersion $protocolVersion Protocol version
     * @return void
     */
    public function useProtocol(ProtocolVersion $protocolVersion): void;
}
