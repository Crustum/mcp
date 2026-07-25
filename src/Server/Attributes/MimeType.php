<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Attributes;

use Attribute;

/**
 * MCP server MIME type attribute.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class MimeType extends ServerAttribute
{
}
