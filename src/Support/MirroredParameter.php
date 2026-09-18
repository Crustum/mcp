<?php
declare(strict_types=1);

namespace Crustum\Mcp\Support;

use Crustum\Mcp\Exception\MirroredParameterException;
use Crustum\Mcp\Transport\HeaderValue;

/**
 * A single tool parameter that is mirrored into a request header.
 */
class MirroredParameter
{
    /**
     * Create a new mirrored parameter.
     *
     * @param \Crustum\Mcp\Support\SchemaPath $path Schema path to the parameter
     * @param string $name Header name token
     * @param \Crustum\Mcp\Support\MirroredParameterType $type Parameter type
     */
    public function __construct(
        public readonly SchemaPath $path,
        public readonly string $name,
        public readonly MirroredParameterType $type,
    ) {
    }

    /**
     * Get the header name this parameter is mirrored into.
     *
     * @return string
     */
    public function header(): string
    {
        return MirroredParameters::PREFIX . $this->name;
    }

    /**
     * Read the raw argument value at this parameter's schema path.
     *
     * @param array<string, mixed> $arguments Tool arguments
     * @return mixed
     */
    public function raw(array $arguments): mixed
    {
        return $this->path->valueIn($arguments);
    }

    /**
     * Convert a value to its header representation.
     *
     * @param mixed $value Argument value
     * @return string|null
     */
    public function stringify(mixed $value): ?string
    {
        return $this->type->stringify($value);
    }

    /**
     * Build the header value for the given arguments.
     *
     * @param array<string, mixed> $arguments Tool arguments
     * @return \Crustum\Mcp\Transport\HeaderValue|null
     */
    public function value(array $arguments): ?HeaderValue
    {
        $value = $this->raw($arguments);

        if ($value === null) {
            return null;
        }

        $stringified = $this->stringify($value);

        if ($stringified === null) {
            throw new MirroredParameterException(
                "The [{$this->path}] argument cannot be mirrored into the [{$this->header()}] header as a [{$this->type->value}].",
            );
        }

        return new HeaderValue($stringified);
    }
}
