<?php
declare(strict_types=1);

namespace Crustum\Mcp\Client\Primitives;

use Cake\Utility\Hash;
use Crustum\Mcp\Exception\ClientException;

/**
 * MCP resource primitive.
 */
class Resource
{
    /**
     * Create a new resource primitive.
     *
     * @param string $uri Resource URI
     * @param string $name Resource name
     * @param string|null $title Resource title
     * @param string|null $description Resource description
     * @param string|null $mimeType Resource MIME type
     * @param int|null $size Resource size in bytes
     * @param array<string, mixed> $annotations Resource annotations
     * @param array<string, mixed>|null $meta Resource metadata
     */
    public function __construct(
        public readonly string $uri,
        public readonly string $name,
        public readonly ?string $title,
        public readonly ?string $description,
        public readonly ?string $mimeType,
        public readonly ?int $size,
        public readonly array $annotations,
        public readonly ?array $meta,
    ) {
    }

    /**
     * Create a resource primitive from a server payload.
     *
     * @param array<string, mixed> $payload Resource list payload
     * @return self
     */
    public static function from(array $payload): self
    {
        $uri = Hash::get($payload, 'uri');
        $name = Hash::get($payload, 'name');
        $title = Hash::get($payload, 'title');
        $description = Hash::get($payload, 'description');
        $mimeType = Hash::get($payload, 'mimeType');
        $size = Hash::get($payload, 'size');
        $annotations = Hash::get($payload, 'annotations', []);
        $meta = Hash::get($payload, '_meta');

        if (
            !is_string($uri)
            || trim($uri) === ''
            || !is_string($name)
            || trim($name) === ''
            || !is_array($annotations)
            || ($title !== null && !is_string($title))
            || ($description !== null && !is_string($description))
            || ($mimeType !== null && !is_string($mimeType))
            || ($size !== null && !is_int($size))
            || ($meta !== null && !is_array($meta))
        ) {
            throw new ClientException('Invalid resource payload from server.');
        }

        return new self(
            uri: $uri,
            name: $name,
            title: $title,
            description: $description,
            mimeType: $mimeType,
            size: $size,
            annotations: $annotations,
            meta: $meta,
        );
    }
}
