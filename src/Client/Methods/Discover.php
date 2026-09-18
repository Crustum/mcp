<?php
declare(strict_types=1);

namespace Crustum\Mcp\Client\Methods;

use Crustum\Mcp\Client\Contracts\Method;
use Crustum\Mcp\Client\Protocol;
use Crustum\Mcp\Client\Schema\DiscoverResult;

/**
 * MCP server/discover JSON-RPC method.
 *
 * @implements \Crustum\Mcp\Client\Contracts\Method<\Crustum\Mcp\Client\Schema\DiscoverResult>
 */
class Discover implements Method
{
    /**
     * @inheritDoc
     */
    public function method(): string
    {
        return 'server/discover';
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
    public function handle(Protocol $protocol): DiscoverResult
    {
        return DiscoverResult::from($protocol->dispatch($this));
    }
}
