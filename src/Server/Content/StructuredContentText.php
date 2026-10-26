<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Content;

/**
 * Text content mirroring structured content.
 *
 * Marks the JSON text block created by `Response::structured()` as the mirror
 * of its structured payload, so tool catalogs can drop the duplicate instead
 * of charging it twice against output limits.
 */
class StructuredContentText extends Text
{
}
