<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Tools\Annotations;

use Attribute;

/**
 * MCP idempotent hint annotation attribute.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class IsIdempotent extends ToolAnnotation
{
    /**
     * @param bool $value Whether the tool is idempotent
     */
    public function __construct(public bool $value = true)
    {
    }

    /**
     * Get the MCP annotation key.
     *
     * @return string
     */
    public function key(): string
    {
        return 'idempotentHint';
    }
}
