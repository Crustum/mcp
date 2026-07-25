<?php
declare(strict_types=1);

namespace Crustum\Mcp\Client\Methods;

use Crustum\Mcp\Client\Contracts\Method;
use Crustum\Mcp\Client\Protocol;

/**
 * MCP ping JSON-RPC method.
 *
 * @implements \Crustum\Mcp\Client\Contracts\Method<void>
 */
class Ping implements Method
{
    /**
     * @inheritDoc
     */
    public function method(): string
    {
        return 'ping';
    }

    /**
     * @inheritDoc
     */
    public function params(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function handle(Protocol $protocol): void
    {
        $protocol->dispatch($this);
    }
}
