<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Annotations;

use Attribute;
use InvalidArgumentException;

/**
 * MCP priority annotation attribute.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Priority extends Annotation
{
    /**
     * @param float $value Priority value between 0.0 and 1.0
     */
    public function __construct(public float $value)
    {
        if ($value < 0.0 || $value > 1.0) {
            throw new InvalidArgumentException(
                "Priority must be between 0.0 and 1.0, got {$value}",
            );
        }
    }

    /**
     * Get the MCP annotation key.
     *
     * @return string
     */
    public function key(): string
    {
        return 'priority';
    }
}
