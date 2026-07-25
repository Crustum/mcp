<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Contracts;

/**
 * MCP annotation contract for server primitives.
 *
 * @property mixed $value Annotation value
 */
interface Annotation
{
    /**
     * Get the MCP annotation key.
     *
     * @return string
     */
    public function key(): string;
}
