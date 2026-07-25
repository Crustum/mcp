<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Tools\Annotations;

use Attribute;

/**
 * MCP read-only hint annotation attribute.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class IsReadOnly extends ToolAnnotation
{
    /**
     * @param bool $value Whether the tool is read-only
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
        return 'readOnlyHint';
    }
}
