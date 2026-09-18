<?php
declare(strict_types=1);

namespace Crustum\Mcp\Client\Methods\Tools;

use Crustum\Mcp\Client\Contracts\Method;
use Crustum\Mcp\Client\Contracts\MirrorsParameters;
use Crustum\Mcp\Client\Protocol;
use Crustum\Mcp\Client\Schema\ToolResult;
use Crustum\Mcp\Support\MirroredParameters;

/**
 * MCP tools/call JSON-RPC method.
 *
 * @implements \Crustum\Mcp\Client\Contracts\Method<\Crustum\Mcp\Client\Schema\ToolResult>
 */
class CallTool implements Method, MirrorsParameters
{
    /**
     * Create a new tools/call method.
     *
     * @param string $name Tool name
     * @param array<string, mixed> $arguments Tool arguments
     * @param \Crustum\Mcp\Support\MirroredParameters|null $mirroredParameters Mirrored parameters
     */
    public function __construct(
        protected string $name,
        protected array $arguments = [],
        protected ?MirroredParameters $mirroredParameters = null,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function requestHeaders(): array
    {
        return $this->mirroredParameters?->headers($this->arguments) ?? [];
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
