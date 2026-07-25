<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Content;

use Crustum\Mcp\Server\Contracts\Content;
use Crustum\Mcp\Server\Prompt;
use Crustum\Mcp\Server\Resource;
use Crustum\Mcp\Server\Tool;
use Crustum\Mcp\Server\Trait\HasMetaTrait;

/**
 * MCP image content value object.
 */
class Image implements Content
{
    use HasMetaTrait;

    /**
     * @param string $data Raw image bytes
     * @param string $mimeType Image MIME type
     */
    public function __construct(protected string $data, protected string $mimeType = 'image/png')
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
            'blob' => base64_encode($this->data),
            'uri' => $resource->uri(),
            'mimeType' => $this->mimeType,
        ]);
    }

    /**
     * Render the content as a string.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->data;
    }

    /**
     * Convert the content to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->mergeMeta([
            'type' => 'image',
            'data' => base64_encode($this->data),
            'mimeType' => $this->mimeType,
        ]);
    }
}
