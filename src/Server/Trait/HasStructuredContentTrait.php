<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Trait;

/**
 * Stores optional MCP structured content on server responses.
 */
trait HasStructuredContentTrait
{
    /**
     * Optional structured content payload.
     *
     * @var array<string, mixed>|null
     */
    protected ?array $structuredContent = null;

    /**
     * Set structured content data.
     *
     * @param array<string, mixed> $structuredContent Structured content payload
     * @return void
     */
    public function setStructuredContent(array $structuredContent): void
    {
        $this->structuredContent ??= [];

        $this->structuredContent = array_merge($this->structuredContent, $structuredContent);
    }

    /**
     * Merge stored structured content into a base array when present.
     *
     * @param array<string, mixed> $baseArray Base payload array
     * @return array<string, mixed>
     */
    public function mergeStructuredContent(array $baseArray): array
    {
        if ($this->structuredContent === null) {
            return $baseArray;
        }

        return array_merge($baseArray, ['structuredContent' => $this->structuredContent]);
    }
}
