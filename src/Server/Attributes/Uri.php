<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Attributes;

use Attribute;

/**
 * MCP server URI attribute.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Uri extends ServerAttribute
{
}
