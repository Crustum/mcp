<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Attributes;

use Attribute;

/**
 * MCP server name attribute.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Name extends ServerAttribute
{
}
