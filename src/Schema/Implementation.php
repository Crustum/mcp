<?php
declare(strict_types=1);

namespace Crustum\Mcp\Schema;

use Cake\Utility\Hash;
use Crustum\Mcp\Contracts\Arrayable;
use Crustum\Mcp\Enums\IconTheme;
use InvalidArgumentException;

/**
 * MCP implementation metadata schema value.
 *
 * @implements \Crustum\Mcp\Contracts\Arrayable<string, mixed>
 */
class Implementation implements Arrayable
{
    /**
     * Create a new implementation instance.
     *
     * @param string $name Implementation name
     * @param string $version Implementation version
     * @param string|null $title Implementation title
     * @param string|null $description Implementation description
     * @param array<\Crustum\Mcp\Schema\Icon> $icons Implementation icons
     * @param string|null $websiteUrl Implementation website URL
     */
    public function __construct(
        public string $name,
        public string $version,
        public ?string $title = null,
        public ?string $description = null,
        public array $icons = [],
        public ?string $websiteUrl = null,
    ) {
    }

    /**
     * Get the implementation as an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'version' => $this->version,
            'title' => $this->title,
            'description' => $this->description,
            'icons' => $this->icons === [] ? null : array_map(
                static fn(Icon $icon): array => $icon->toArray(),
                $this->icons,
            ),
            'websiteUrl' => $this->websiteUrl,
        ], static fn(mixed $value): bool => $value !== null);
    }

    /**
     * Create an implementation from an arbitrary payload.
     *
     * Returns null when the payload is not a well-formed implementation.
     *
     * @param mixed $data Implementation payload
     * @return self|null
     */
    public static function from(mixed $data): ?self
    {
        if (!is_array($data)) {
            return null;
        }

        try {
            return new self(
                name: self::stringAt($data, 'name'),
                version: self::stringAt($data, 'version'),
                title: self::stringAt($data, 'title', '') ?: null,
                description: self::stringAt($data, 'description', '') ?: null,
                icons: array_map(function (mixed $icon): Icon {
                    $icon = is_array($icon) ? $icon : [];

                    return Icon::from(
                        src: self::stringAt($icon, 'src'),
                        mimeType: self::stringAt($icon, 'mimeType', '') ?: null,
                        sizes: array_values(array_filter(self::arrayAt($icon, 'sizes', []), is_string(...))),
                        theme: IconTheme::tryFrom(self::stringAt($icon, 'theme', '')),
                    );
                }, self::arrayAt($data, 'icons', [])),
                websiteUrl: self::stringAt($data, 'websiteUrl', '') ?: null,
            );
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * Read a string value from an array or throw.
     *
     * @param array<array-key, mixed> $array Source array
     * @param string $key Array key
     * @param string|null $default Default value
     * @return string
     */
    protected static function stringAt(array $array, string $key, ?string $default = null): string
    {
        $value = Hash::get($array, $key, $default);

        if (!is_string($value)) {
            throw new InvalidArgumentException("The [{$key}] value must be a string.");
        }

        return $value;
    }

    /**
     * Read an array value from an array or throw.
     *
     * @param array<array-key, mixed> $array Source array
     * @param string $key Array key
     * @param array<array-key, mixed>|null $default Default value
     * @return array<array-key, mixed>
     */
    protected static function arrayAt(array $array, string $key, ?array $default = null): array
    {
        $value = Hash::get($array, $key, $default);

        if (!is_array($value)) {
            throw new InvalidArgumentException("The [{$key}] value must be an array.");
        }

        return $value;
    }
}
