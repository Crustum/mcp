<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Completions;

/**
 * Direct completion response with pre-resolved values.
 */
class DirectCompletionResponse extends CompletionResponse
{
    /**
     * Resolve completion values for the current partial input.
     *
     * @param string $value Current partial value
     * @return static
     */
    public function resolve(string $value): static
    {
        return $this;
    }
}
