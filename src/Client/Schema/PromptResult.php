<?php
declare(strict_types=1);

namespace Crustum\Mcp\Client\Schema;

use Cake\Utility\Hash;
use Crustum\Mcp\Exception\ClientException;
use Stringable;

/**
 * MCP prompts/get result.
 */
class PromptResult implements Stringable
{
    /**
     * Create a new prompt result.
     *
     * @param array<int, array<string, mixed>> $messages Prompt messages
     * @param string|null $description Prompt description
     * @param array<string, mixed>|null $meta Result metadata
     */
    public function __construct(
        public array $messages,
        public ?string $description = null,
        public ?array $meta = null,
    ) {
    }

    /**
     * Create a prompt result from a JSON-RPC result payload.
     *
     * @param array<string, mixed> $result Prompt result payload
     * @return self
     */
    public static function from(array $result): self
    {
        $messages = Hash::get($result, 'messages', []);
        $description = Hash::get($result, 'description');
        $meta = Hash::get($result, '_meta');

        if (!is_array($messages) || ($description !== null && !is_string($description))) {
            throw new ClientException('Invalid prompts/get result from server.');
        }

        return new self(
            messages: array_values(array_filter($messages, is_array(...))),
            description: $description,
            meta: is_array($meta) ? $meta : null,
        );
    }

    /**
     * Extract concatenated text content from prompt messages.
     *
     * @return string
     */
    public function text(): string
    {
        $parts = [];

        foreach ($this->messages as $message) {
            $content = Hash::get($message, 'content');

            if (!is_array($content)) {
                continue;
            }

            $text = Hash::get($content, 'text');

            if (Hash::get($content, 'type') === 'text' && is_string($text)) {
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
