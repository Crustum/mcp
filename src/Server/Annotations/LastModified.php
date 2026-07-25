<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Annotations;

use Attribute;
use DateTimeImmutable;
use Exception;
use InvalidArgumentException;

/**
 * MCP last modified annotation attribute.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class LastModified extends Annotation
{
    /**
     * @param string $value ISO 8601 timestamp
     */
    public function __construct(public string $value)
    {
        try {
            new DateTimeImmutable($value);
        } catch (Exception $exception) {
            throw new InvalidArgumentException(
                "LastModified must be a valid ISO 8601 timestamp, got '{$value}'",
                $exception->getCode(),
                $exception,
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
        return 'lastModified';
    }
}
