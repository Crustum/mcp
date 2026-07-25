<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Attributes;

use Attribute;

/**
 * MCP server instructions attribute.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Instructions extends ServerAttribute
{
}
