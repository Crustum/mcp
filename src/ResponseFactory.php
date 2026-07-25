<?php
declare(strict_types=1);

namespace Crustum\Mcp;

use Cake\Collection\Collection;
use Crustum\Mcp\Server\Trait\HasMetaTrait;
use Crustum\Mcp\Server\Trait\HasStructuredContentTrait;
use Crustum\Mcp\Trait\ConditionableTrait;
use Crustum\Mcp\Trait\MacroableTrait;
use InvalidArgumentException;

/**
 * Factory for MCP responses with shared metadata and structured content.
 */
class ResponseFactory
{
    use ConditionableTrait;
    use HasMetaTrait;
    use HasStructuredContentTrait;
    use MacroableTrait;

    /**
     * Wrapped response instances.
     *
     * @var \Cake\Collection\Collection<int, \Crustum\Mcp\Response>
     */
    protected Collection $responses;

    /**
     * @param \Crustum\Mcp\Response|array<int, mixed> $responses Response instances
     */
    public function __construct(Response|array $responses)
    {
        $wrapped = is_array($responses) ? $responses : [$responses];

        foreach ($wrapped as $index => $response) {
            if (!$response instanceof Response) {
                throw new InvalidArgumentException(
                    "Invalid response type at index {$index}: Expected " . Response::class . ', but received ' . get_debug_type($response) . '.',
                );
            }
        }

        $this->responses = new Collection($wrapped);
    }

    /**
     * Attach metadata to the factory payload.
     *
     * @param array<string, mixed>|string $meta Metadata array or key name
     * @param mixed $value Metadata value when using key/value signature
     * @return static
     */
    public function withMeta(array|string $meta, mixed $value = null): static
    {
        $this->setMeta($meta, $value);

        return $this;
    }

    /**
     * Attach structured content to the factory payload.
     *
     * @param array<string, mixed> $structuredContent Structured content payload
     * @return static
     */
    public function withStructuredContent(array $structuredContent): static
    {
        $this->setStructuredContent($structuredContent);

        return $this;
    }

    /**
     * Get wrapped response instances.
     *
     * @return \Cake\Collection\Collection<int, \Crustum\Mcp\Response>
     */
    public function responses(): Collection
    {
        return $this->responses;
    }

    /**
     * Get factory metadata.
     *
     * @return array<string, mixed>|null
     */
    public function getMeta(): ?array
    {
        return $this->meta;
    }

    /**
     * Get factory structured content.
     *
     * @return array<string, mixed>|null
     */
    public function getStructuredContent(): ?array
    {
        return $this->structuredContent;
    }
}
