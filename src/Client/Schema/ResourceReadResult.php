<?php
declare(strict_types=1);

namespace Crustum\Mcp\Client\Schema;

use Cake\Utility\Hash;
use Crustum\Mcp\Exception\ClientException;
use Stringable;

/**
 * MCP resources/read result.
 */
class ResourceReadResult implements Stringable
{
    /**
     * Create a new resource read result.
     *
     * @param array<int, array<string, mixed>> $contents Resource contents
     * @param array<string, mixed>|null $meta Result metadata
     */
    public function __construct(
        public readonly array $contents,
        public readonly ?array $meta = null,
    ) {
    }

    /**
     * Create a resource read result from a JSON-RPC result payload.
     *
     * @param array<string, mixed> $result Resource read result payload
     * @return self
     */
    public static function from(array $result): self
    {
        $contents = Hash::get($result, 'contents', []);
        $meta = Hash::get($result, '_meta');

        if (!is_array($contents)) {
            throw new ClientException('Invalid resources/read result from server.');
        }

        return new self(
            contents: array_values(array_filter($contents, is_array(...))),
            meta: is_array($meta) ? $meta : null,
        );
    }

    /**
     * Get the first available MIME type from resource contents.
     *
     * @return string|null
     */
    public function mimeType(): ?string
    {
        foreach ($this->contents as $content) {
            $mimeType = Hash::get($content, 'mimeType');

            if (is_string($mimeType) && $mimeType !== '') {
                return $mimeType;
            }
        }

        return null;
    }

    /**
     * Extract concatenated resource content as a string.
     *
     * @return string
     */
    public function content(): string
    {
        $parts = [];

        foreach ($this->contents as $content) {
            $text = Hash::get($content, 'text');

            if (is_string($text)) {
                $parts[] = $text;

                continue;
            }

            $blob = Hash::get($content, 'blob');

            if (is_string($blob)) {
                $decoded = base64_decode($blob, true);

                if ($decoded !== false) {
                    $parts[] = $decoded;
                }
            }
        }

        return implode('', $parts);
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->content();
    }
}
