<?php
declare(strict_types=1);

namespace Crustum\Mcp\Client\Schema;

use Cake\Utility\Hash;
use Crustum\Mcp\Exception\ClientException;
use Stringable;

/**
 * MCP tools/call result.
 */
class ToolResult implements Stringable
{
    /**
     * Create a new tool result.
     *
     * @param array<int, array<string, mixed>> $content Tool result content blocks
     * @param bool $isError Whether the tool execution failed
     * @param array<string, mixed>|null $structuredContent Structured tool output
     * @param array<string, mixed>|null $meta Result metadata
     */
    public function __construct(
        public array $content,
        public bool $isError,
        public ?array $structuredContent = null,
        public ?array $meta = null,
    ) {
    }

    /**
     * Create a tool result from a JSON-RPC result payload.
     *
     * @param array<string, mixed> $result Tool call result payload
     * @return self
     */
    public static function from(array $result): self
    {
        $content = Hash::get($result, 'content', []);
        $isError = Hash::get($result, 'isError', false);
        $structuredContent = Hash::get($result, 'structuredContent');
        $meta = Hash::get($result, '_meta');

        if (!is_array($content) || !is_bool($isError)) {
            throw new ClientException('Invalid tools/call result from server.');
        }

        return new self(
            content: array_values(array_filter($content, is_array(...))),
            isError: $isError,
            structuredContent: is_array($structuredContent) ? $structuredContent : null,
            meta: is_array($meta) ? $meta : null,
        );
    }

    /**
     * Extract concatenated text content from tool result blocks.
     *
     * @return string
     */
    public function text(): string
    {
        $parts = [];

        foreach ($this->content as $item) {
            $text = Hash::get($item, 'text');

            if (Hash::get($item, 'type') === 'text' && is_string($text)) {
                $parts[] = $text;
            }
        }

        return implode('', $parts);
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->text();
    }
}
