<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Content;

use Crustum\Mcp\Schema\Icon;
use Crustum\Mcp\Server\Contracts\Content;
use Crustum\Mcp\Server\Prompt;
use Crustum\Mcp\Server\Resource;
use Crustum\Mcp\Server\Tool;
use Crustum\Mcp\Server\Trait\HasMetaTrait;
use InvalidArgumentException;

/**
 * MCP resource link content value object.
 */
class ResourceLink implements Content
{
    use HasMetaTrait;

    /**
     * @param string $uri Linked resource URI
     * @param string $name Linked resource name
     * @param string|null $mimeType Linked resource MIME type
     * @param string|null $title Linked resource title
     * @param string|null $description Linked resource description
     * @param int|null $size Linked resource size in bytes
     * @param array<string, mixed> $annotations Resource annotations
     * @param list<\Crustum\Mcp\Schema\Icon> $icons Resource icons
     */
    public function __construct(
        protected string $uri,
        protected string $name,
        protected ?string $mimeType = null,
        protected ?string $title = null,
        protected ?string $description = null,
        protected ?int $size = null,
        protected array $annotations = [],
        protected array $icons = [],
    ) {
    }

    /**
     * Convert the content to a tool response payload.
     *
     * @param \Crustum\Mcp\Server\Tool $tool Tool instance
     * @return array<string, mixed>
     */
    public function toTool(Tool $tool): array
    {
        return $this->toArray();
    }

    /**
     * Convert the content to a prompt response payload.
     *
     * @param \Crustum\Mcp\Server\Prompt $prompt Prompt instance
     * @return array<string, mixed>
     */
    public function toPrompt(Prompt $prompt): array
    {
        return $this->toArray();
    }

    /**
     * Convert the content to a resource response payload.
     *
     * @param \Crustum\Mcp\Server\Resource $resource Resource instance
     * @return array<string, mixed>
     */
    public function toResource(Resource $resource): array
    {
        throw new InvalidArgumentException(
            'ResourceLink content may not be used in resources.',
        );
    }

    /**
     * Render the content as a string.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->uri;
    }

    /**
     * Convert the content to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = array_filter([
            'type' => 'resource_link',
            'uri' => $this->uri,
            'name' => $this->name,
            'title' => $this->title,
            'description' => $this->description,
            'mimeType' => $this->mimeType,
            'size' => $this->size,
        ], fn(mixed $value): bool => $value !== null);

        if ($this->annotations !== []) {
            $data['annotations'] = $this->annotations;
        }

        if ($this->icons !== []) {
            $data['icons'] = array_map(fn(Icon $icon): array => $icon->toArray(), $this->icons);
        }

        return $this->mergeMeta($data);
    }
}
