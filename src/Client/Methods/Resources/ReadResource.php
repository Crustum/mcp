<?php
declare(strict_types=1);

namespace Crustum\Mcp\Client\Methods\Resources;

use Crustum\Mcp\Client\Contracts\Method;
use Crustum\Mcp\Client\Protocol;
use Crustum\Mcp\Client\Schema\ResourceReadResult;

/**
 * MCP resources/read JSON-RPC method.
 *
 * @implements \Crustum\Mcp\Client\Contracts\Method<\Crustum\Mcp\Client\Schema\ResourceReadResult>
 */
class ReadResource implements Method
{
    /**
     * Create a new resources/read method.
     *
     * @param string $uri Resource URI
     */
    public function __construct(
        protected string $uri,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function method(): string
    {
        return 'resources/read';
    }

    /**
     * @inheritDoc
     */
    public function params(): array
    {
        return ['uri' => $this->uri];
    }

    /**
     * @inheritDoc
     */
    public function handle(Protocol $protocol): ResourceReadResult
    {
        return ResourceReadResult::from($protocol->dispatch($this));
    }
}
