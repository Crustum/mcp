<?php
declare(strict_types=1);

namespace Crustum\Mcp\Schema;

use Cake\Core\Configure;
use Crustum\Mcp\Contracts\Arrayable;
use Crustum\Mcp\Enums\IconTheme;

/**
 * MCP icon schema value.
 *
 * @implements \Crustum\Mcp\Contracts\Arrayable<string, mixed>
 */
class Icon implements Arrayable
{
    /**
     * Create a new icon instance.
     *
     * @param string $src Icon source URL or path
     * @param string|null $mimeType Icon MIME type
     * @param list<string> $sizes Supported icon sizes
     * @param \Crustum\Mcp\Enums\IconTheme|null $theme Icon theme
     */
    public function __construct(
        public string $src,
        public ?string $mimeType = null,
        public array $sizes = [],
        public ?IconTheme $theme = null,
    ) {
    }

    /**
     * Create an icon from a source path or URL.
     *
     * @param string $src Icon source URL or path
     * @param string|null $mimeType Icon MIME type
     * @param list<string> $sizes Supported icon sizes
     * @param \Crustum\Mcp\Enums\IconTheme|null $theme Icon theme
     * @return self
     */
    public static function from(string $src, ?string $mimeType = null, array $sizes = [], ?IconTheme $theme = null): self
    {
        if (parse_url($src, PHP_URL_SCHEME) !== null) {
            return new self($src, $mimeType, $sizes, $theme);
        }

        $base = (string)Configure::read('Mcp.asset_base_url', '');
        $resolved = $base !== '' ? rtrim($base, '/') . '/' . ltrim($src, '/') : '/' . ltrim($src, '/');

        return new self($resolved, $mimeType, $sizes, $theme);
    }

    /**
     * Get the icon as an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'src' => $this->src,
            'mimeType' => $this->mimeType,
            'sizes' => $this->sizes === [] ? null : $this->sizes,
            'theme' => $this->theme?->value,
        ], static fn(mixed $value): bool => $value !== null);
    }
}
