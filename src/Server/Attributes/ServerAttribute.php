<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Attributes;

/**
 * Base MCP server PHP attribute.
 */
abstract class ServerAttribute
{
    /**
     * @param string $value Attribute value
     */
    public function __construct(public string $value)
    {
    }
}
