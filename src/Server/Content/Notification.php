<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Content;

use Crustum\Mcp\Server\Contracts\Content;
use Crustum\Mcp\Server\Prompt;
use Crustum\Mcp\Server\Resource;
use Crustum\Mcp\Server\Tool;
use Crustum\Mcp\Server\Trait\HasMetaTrait;

/**
 * MCP JSON-RPC notification content value object.
 */
class Notification implements Content
{
    use HasMetaTrait;

    /**
     * @param string $method Notification method name
     * @param array<string, mixed> $params Notification parameters
     */
    public function __construct(protected string $method, protected array $params)
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
        return $this->toArray();
    }

    /**
     * Render the content as a string.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->method;
    }

    /**
     * Convert the content to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $params = $this->params;

        if ($this->meta !== null && $this->meta !== [] && !array_key_exists('_meta', $params)) {
            $params['_meta'] = $this->meta;
        }

        return [
            'method' => $this->method,
            'params' => $params,
        ];
    }
}
