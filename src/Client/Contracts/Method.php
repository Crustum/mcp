<?php
declare(strict_types=1);

namespace Crustum\Mcp\Client\Contracts;

use Crustum\Mcp\Client\Protocol;

/**
 * MCP client JSON-RPC method contract.
 *
 * @template TResult
 */
interface Method
{
    /**
     * Get the JSON-RPC method name.
     *
     * @return string
     */
    public function method(): string;

    /**
     * Get the JSON-RPC method parameters.
     *
     * @return array<string, mixed>
     */
    public function params(): array;

    /**
     * Handle the JSON-RPC response for this method.
     *
     * @param \Crustum\Mcp\Client\Protocol $protocol Client protocol instance
     * @return TResult
     */
    public function handle(Protocol $protocol);
}
