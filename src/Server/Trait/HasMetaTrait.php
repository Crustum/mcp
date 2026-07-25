<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Trait;

use InvalidArgumentException;

/**
 * Stores optional MCP _meta payload on server primitives.
 */
trait HasMetaTrait
{
    /**
     * Optional MCP metadata payload.
     *
     * @var array<string, mixed>|null
     */
    protected ?array $meta = null;

    /**
     * Set metadata using either a key/value pair or an associative array.
     *
     * @param array<string, mixed>|string $meta Metadata array or key name
     * @param mixed $value Metadata value when using key/value signature
     * @return void
     */
    public function setMeta(array|string $meta, mixed $value = null): void
    {
        $this->meta ??= [];

        if (!is_array($meta)) {
            if ($value === null) {
                throw new InvalidArgumentException('Value is required when using key-value signature.');
            }

            $this->meta[$meta] = $value;

            return;
        }

        $this->meta = array_merge($this->meta, $meta);
    }

    /**
     * Merge stored metadata into a base array when present.
     *
     * @param array<string, mixed> $baseArray Base payload array
     * @return array<string, mixed>
     */
    public function mergeMeta(array $baseArray): array
    {
        return ($meta = $this->meta)
            ? [...$baseArray, '_meta' => $meta]
            : $baseArray;
    }
}
