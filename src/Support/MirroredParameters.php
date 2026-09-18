<?php
declare(strict_types=1);

namespace Crustum\Mcp\Support;

use Cake\Collection\Collection;
use Countable;
use Crustum\Mcp\Exception\MirroredParameterException;
use Crustum\Mcp\Transport\HeaderValue;

/**
 * Tool parameters annotated to be mirrored into request headers.
 */
class MirroredParameters implements Countable
{
    /**
     * Schema annotation that marks a property for header mirroring.
     */
    public const ANNOTATION = 'x-mcp-header';

    /**
     * Prefix for headers that carry mirrored tool parameters.
     */
    public const PREFIX = 'Mcp-Param-';

    /**
     * Valid HTTP header name token.
     */
    protected const TOKEN = '/^[!#$%&\'*+\-.^_`|~0-9A-Za-z]+$/D';

    /**
     * Create a new mirrored parameters set.
     *
     * @param array<int, \Crustum\Mcp\Support\MirroredParameter> $parameters Mirrored parameters
     */
    protected function __construct(protected array $parameters)
    {
    }

    /**
     * Create an empty set of mirrored parameters.
     *
     * @return self
     */
    public static function none(): self
    {
        return new self([]);
    }

    /**
     * Parse mirrored parameters out of an input schema.
     *
     * @param array<string, mixed> $inputSchema Tool input schema
     * @return self
     */
    public static function fromSchema(array $inputSchema): self
    {
        $parameters = self::parse($inputSchema, SchemaPath::root());

        $seen = [];
        $duplicates = [];

        foreach ($parameters as $parameter) {
            $lowered = strtolower($parameter->name);

            if (isset($seen[$lowered])) {
                $duplicates[] = $parameter->name;
            }

            $seen[$lowered] = true;
        }

        if ($duplicates !== []) {
            throw new MirroredParameterException(
                'the [' . self::ANNOTATION . '] value [' . implode(', ', array_unique($duplicates)) . '] is used more than once',
            );
        }

        return new self($parameters);
    }

    /**
     * Build the request headers for a set of tool arguments.
     *
     * @param array<string, mixed> $arguments Tool arguments
     * @return array<string, string>
     */
    public function headers(array $arguments): array
    {
        $headers = [];

        foreach ($this->parameters as $parameter) {
            $value = $parameter->value($arguments);

            if ($value instanceof HeaderValue) {
                $headers[$parameter->header()] = (string)$value;
            }
        }

        return $headers;
    }

    /**
     * Get the mirrored parameters as a collection.
     *
     * @return \Cake\Collection\Collection<int, \Crustum\Mcp\Support\MirroredParameter>
     */
    public function all(): Collection
    {
        return new Collection($this->parameters);
    }

    /**
     * Get the number of mirrored parameters.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->parameters);
    }

    /**
     * Recursively collect mirrored parameters from statically reachable properties.
     *
     * @param array<string, mixed> $schema Schema node
     * @param \Crustum\Mcp\Support\SchemaPath $path Path to the schema node
     * @return list<\Crustum\Mcp\Support\MirroredParameter>
     */
    protected static function parse(array $schema, SchemaPath $path): array
    {
        $parameters = [];

        foreach ($schema as $key => $value) {
            if ($key === self::ANNOTATION) {
                throw new MirroredParameterException(
                    'an [' . self::ANNOTATION . '] annotation sits outside the statically reachable properties',
                );
            }

            if (!is_array($value)) {
                continue;
            }

            if ($key !== 'properties') {
                if (self::containsAnnotation($value)) {
                    throw new MirroredParameterException(
                        'an [' . self::ANNOTATION . '] annotation sits outside the statically reachable properties',
                    );
                }

                continue;
            }

            foreach ($value as $property => $child) {
                if (!is_array($child)) {
                    continue;
                }

                $childPath = $path->child((string)$property);

                if (array_key_exists(self::ANNOTATION, $child)) {
                    $parameters[] = self::parameter($child, $childPath);

                    unset($child[self::ANNOTATION]);
                }

                $parameters = [...$parameters, ...self::parse($child, $childPath)];
            }
        }

        return $parameters;
    }

    /**
     * Build a mirrored parameter from an annotated property schema.
     *
     * @param array<string, mixed> $schema Property schema
     * @param \Crustum\Mcp\Support\SchemaPath $path Path to the property
     * @return \Crustum\Mcp\Support\MirroredParameter
     */
    protected static function parameter(array $schema, SchemaPath $path): MirroredParameter
    {
        $name = $schema[self::ANNOTATION];

        if (!is_string($name) || preg_match(self::TOKEN, $name) !== 1) {
            throw new MirroredParameterException(
                'the [' . self::ANNOTATION . "] value on [{$path}] is not a valid header name token",
            );
        }

        $type = MirroredParameterType::fromSchema($schema['type'] ?? null);

        if (!$type instanceof MirroredParameterType) {
            throw new MirroredParameterException(
                'the [' . self::ANNOTATION . "] annotation on [{$path}] must sit on a string, integer, or boolean",
            );
        }

        return new MirroredParameter($path, $name, $type);
    }

    /**
     * Determine whether a schema node contains the annotation anywhere within it.
     *
     * @param array<array-key, mixed> $schema Schema node
     * @return bool
     */
    protected static function containsAnnotation(array $schema): bool
    {
        foreach ($schema as $key => $value) {
            if ($key === self::ANNOTATION) {
                return true;
            }

            if (is_array($value) && self::containsAnnotation($value)) {
                return true;
            }
        }

        return false;
    }
}
