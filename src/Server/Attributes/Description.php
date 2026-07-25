<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Attributes;

use Attribute;

/**
 * MCP server description attribute.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Description extends ServerAttribute
{
}
