<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Tools\Annotations;

use Attribute;

/**
 * MCP open-world hint annotation attribute.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class IsOpenWorld extends ToolAnnotation
{
    /**
     * @param bool $value Whether the tool interacts with an open-world environment
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
        return 'openWorldHint';
    }
}
