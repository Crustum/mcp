<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Attributes;

use Attribute;

/**
 * MCP server title attribute.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Title extends ServerAttribute
{
}
