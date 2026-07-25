<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Contracts;

use Crustum\Mcp\Contracts\Arrayable;
use Crustum\Mcp\Server\Prompt;
use Crustum\Mcp\Server\Resource;
use Crustum\Mcp\Server\Tool;
use Stringable;

/**
 * MCP content contract for tool, prompt, and resource responses.
 *
 * @extends \Crustum\Mcp\Contracts\Arrayable<string, mixed>
 */
interface Content extends Arrayable, Stringable
{
    /**
     * Convert the content to a tool response payload.
     *
     * @param \Crustum\Mcp\Server\Tool $tool Tool instance
     * @return array<string, mixed>
     */
    public function toTool(Tool $tool): array;

    /**
     * Convert the content to a prompt response payload.
     *
     * @param \Crustum\Mcp\Server\Prompt $prompt Prompt instance
     * @return array<string, mixed>
     */
    public function toPrompt(Prompt $prompt): array;

    /**
     * Convert the content to a resource response payload.
     *
     * @param \Crustum\Mcp\Server\Resource $resource Resource instance
     * @return array<string, mixed>
     */
    public function toResource(Resource $resource): array;

    /**
     * Set metadata using either a key/value pair or an associative array.
     *
     * @param array<string, mixed>|string $meta Metadata array or key name
     * @param mixed $value Metadata value when using key/value signature
     * @return void
     */
    public function setMeta(array|string $meta, mixed $value = null): void;

    /**
     * Render the content as a string.
     *
     * @return string
     */
    public function __toString(): string;
}
