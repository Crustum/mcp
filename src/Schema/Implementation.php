<?php
declare(strict_types=1);

namespace Crustum\Mcp\Schema;

use Cake\Utility\Hash;
use Crustum\Mcp\Contracts\Arrayable;
use Crustum\Mcp\Enums\IconTheme;

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
     * Create an implementation from an array payload.
     *
     * @param array{
     *     name: string,
     *     version: string,
     *     title?: string,
     *     description?: string,
     *     icons?: array<int, array{src: string, mimeType?: string, sizes?: array<string>, theme?: string}>,
     *     websiteUrl?: string,
     * } $data Implementation payload
     * @return self
     */
    public static function from(array $data): self
    {
        $icons = [];

        foreach (Hash::get($data, 'icons', []) as $icon) {
            if (!is_array($icon)) {
                continue;
            }

            $theme = IconTheme::tryFrom((string)Hash::get($icon, 'theme', ''));

            $icons[] = Icon::from(
                src: (string)Hash::get($icon, 'src'),
                mimeType: Hash::get($icon, 'mimeType'),
                sizes: Hash::get($icon, 'sizes', []),
                theme: $theme instanceof IconTheme ? $theme : null,
            );
        }

        return new self(
            name: (string)Hash::get($data, 'name'),
            version: (string)Hash::get($data, 'version'),
            title: Hash::get($data, 'title'),
            description: Hash::get($data, 'description'),
            icons: $icons,
            websiteUrl: Hash::get($data, 'websiteUrl'),
        );
    }
}
