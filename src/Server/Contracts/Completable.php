<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Contracts;

use Crustum\Mcp\Server\Completions\CompletionResponse;

/**
 * MCP completion contract for prompt and tool arguments.
 */
interface Completable
{
    /**
     * Resolve completion suggestions for an argument value.
     *
     * @param string $argument Argument name
     * @param string $value Current partial value
     * @param array<string, mixed> $context Completion context
     * @return \Crustum\Mcp\Server\Completions\CompletionResponse
     */
    public function complete(string $argument, string $value, array $context): CompletionResponse;
}
