<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Content;

use Crustum\Mcp\Server\Contracts\Content;
use Crustum\Mcp\Server\Prompt;
use Crustum\Mcp\Server\Resource;
use Crustum\Mcp\Server\Tool;
use Crustum\Mcp\Server\Trait\HasMetaTrait;

/**
 * MCP text content value object.
 */
class Text implements Content
{
    use HasMetaTrait;

    /**
     * @param string $text Text content
     */
    public function __construct(protected string $text)
    {
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
        return $this->mergeMeta([
            'text' => $this->text,
            'uri' => $resource->uri(),
            'mimeType' => $resource->mimeType(),
        ]);
    }

    /**
     * Render the content as a string.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->text;
    }

    /**
     * Convert the content to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->mergeMeta([
            'type' => 'text',
            'text' => $this->text,
        ]);
    }
}
