<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Content;

use Crustum\Mcp\Server\Contracts\Content;
use Crustum\Mcp\Server\Prompt;
use Crustum\Mcp\Server\Resource;
use Crustum\Mcp\Server\Tool;
use Crustum\Mcp\Server\Trait\HasMetaTrait;
use InvalidArgumentException;

/**
 * MCP blob content value object.
 */
class Blob implements Content
{
    use HasMetaTrait;

    /**
     * @param string $content Raw blob content
     */
    public function __construct(protected string $content)
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
        throw new InvalidArgumentException(
            'Blob content may not be used in tools.',
        );
    }

    /**
     * Convert the content to a prompt response payload.
     *
     * @param \Crustum\Mcp\Server\Prompt $prompt Prompt instance
     * @return array<string, mixed>
     */
    public function toPrompt(Prompt $prompt): array
    {
        throw new InvalidArgumentException(
            'Blob content may not be used in prompts.',
        );
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
            'blob' => base64_encode($this->content),
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
        return $this->content;
    }

    /**
     * Convert the content to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->mergeMeta([
            'type' => 'blob',
            'blob' => $this->content,
        ]);
    }
}
