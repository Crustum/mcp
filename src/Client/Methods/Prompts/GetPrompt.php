<?php
declare(strict_types=1);

namespace Crustum\Mcp\Client\Methods\Prompts;

use Crustum\Mcp\Client\Contracts\Method;
use Crustum\Mcp\Client\Protocol;
use Crustum\Mcp\Client\Schema\PromptResult;

/**
 * MCP prompts/get JSON-RPC method.
 *
 * @implements \Crustum\Mcp\Client\Contracts\Method<\Crustum\Mcp\Client\Schema\PromptResult>
 */
class GetPrompt implements Method
{
    /**
     * Create a new prompts/get method.
     *
     * @param string $name Prompt name
     * @param array<string, mixed> $arguments Prompt arguments
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
        return 'prompts/get';
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
    public function handle(Protocol $protocol): PromptResult
    {
        return PromptResult::from($protocol->dispatch($this));
    }
}
