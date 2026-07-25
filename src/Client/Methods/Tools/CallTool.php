<?php
declare(strict_types=1);

namespace Crustum\Mcp\Client\Methods\Tools;

use Crustum\Mcp\Client\Contracts\Method;
use Crustum\Mcp\Client\Protocol;
use Crustum\Mcp\Client\Schema\ToolResult;

/**
 * MCP tools/call JSON-RPC method.
 *
 * @implements \Crustum\Mcp\Client\Contracts\Method<\Crustum\Mcp\Client\Schema\ToolResult>
 */
class CallTool implements Method
{
    /**
     * Create a new tools/call method.
     *
     * @param string $name Tool name
     * @param array<string, mixed> $arguments Tool arguments
     */
    public function __construct(
        protected string $name,
        protected array $arguments = [],
    ) {
    }

    /**
     * @inheritDoc
     */
    public function method(): string
    {
        return 'tools/call';
    }

    /**
     * @inheritDoc
     */
    public function params(): array
    {
        return [
            'name' => $this->name,
            'arguments' => (object)$this->arguments,
        ];
    }

    /**
     * @inheritDoc
     */
    public function handle(Protocol $protocol): ToolResult
    {
        return ToolResult::from($protocol->dispatch($this));
    }
}
