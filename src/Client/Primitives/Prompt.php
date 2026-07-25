<?php
declare(strict_types=1);

namespace Crustum\Mcp\Client\Primitives;

use Cake\Utility\Hash;
use Crustum\Mcp\Exception\ClientException;

/**
 * MCP prompt primitive.
 */
class Prompt
{
    /**
     * Create a new prompt primitive.
     *
     * @param string $name Prompt name
     * @param string|null $title Prompt title
     * @param string|null $description Prompt description
     * @param array<int, array<string, mixed>> $arguments Prompt argument definitions
     * @param array<string, mixed>|null $meta Prompt metadata
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $title,
        public readonly ?string $description,
        public readonly array $arguments,
        public readonly ?array $meta,
    ) {
    }

    /**
     * Create a prompt primitive from a server payload.
     *
     * @param array<string, mixed> $payload Prompt list payload
     * @return self
     */
    public static function from(array $payload): self
    {
        $name = Hash::get($payload, 'name');
        $title = Hash::get($payload, 'title');
        $description = Hash::get($payload, 'description');
        $arguments = Hash::get($payload, 'arguments', []);
        $meta = Hash::get($payload, '_meta');

        if (
            !is_string($name)
            || trim($name) === ''
            || !is_array($arguments)
            || ($title !== null && !is_string($title))
            || ($description !== null && !is_string($description))
            || ($meta !== null && !is_array($meta))
        ) {
            throw new ClientException('Invalid prompt payload from server.');
        }

        return new self(
            name: $name,
            title: $title,
            description: $description,
            arguments: array_values(array_filter($arguments, is_array(...))),
            meta: $meta,
        );
    }
}
