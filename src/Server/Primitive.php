<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server;

use Crustum\Mcp\Contracts\Arrayable;
use Crustum\Mcp\Schema\Icon;
use Crustum\Mcp\Server\Attributes\Description;
use Crustum\Mcp\Server\Attributes\Name;
use Crustum\Mcp\Server\Attributes\Title;
use Crustum\Mcp\Server\Trait\HasIconsTrait;
use Crustum\Mcp\Server\Trait\HasMetaTrait;
use Crustum\Mcp\Support\ContainerRegistry;
use Crustum\Mcp\Support\Str;
use ReflectionClass;

/**
 * Base MCP server primitive with shared metadata and icon resolution.
 *
 * @implements \Crustum\Mcp\Contracts\Arrayable<string, mixed>
 */
abstract class Primitive implements Arrayable
{
    use HasIconsTrait;
    use HasMetaTrait;

    /**
     * Primitive name override.
     *
     * @var string
     */
    protected string $name = '';

    /**
     * Primitive title override.
     *
     * @var string
     */
    protected string $title = '';

    /**
     * Primitive description override.
     *
     * @var string
     */
    protected string $description = '';

    /**
     * Get the primitive name.
     *
     * @return string
     */
    public function name(): string
    {
        $attribute = $this->resolveAttribute(Name::class);

        return $attribute !== null
            ? $attribute->value
            : ($this->name !== '' ? $this->name : Str::kebab((new ReflectionClass($this))->getShortName()));
    }

    /**
     * Get the primitive title.
     *
     * @return string
     */
    public function title(): string
    {
        $attribute = $this->resolveAttribute(Title::class);

        return $attribute !== null
            ? $attribute->value
            : ($this->title !== '' ? $this->title : Str::headline((new ReflectionClass($this))->getShortName()));
    }

    /**
     * Get the primitive description.
     *
     * @return string
     */
    public function description(): string
    {
        $attribute = $this->resolveAttribute(Description::class);

        return $attribute !== null
            ? $attribute->value
            : ($this->description !== '' ? $this->description : Str::headline((new ReflectionClass($this))->getShortName()));
    }

    /**
     * Get optional primitive metadata.
     *
     * @return array<string, mixed>|null
     */
    public function meta(): ?array
    {
        return $this->meta;
    }

    /**
     * Get primitive-defined icons.
     *
     * @return list<\Crustum\Mcp\Schema\Icon>
     */
    public function icons(): array
    {
        return [];
    }

    /**
     * Merge resolved icons into a base array when present.
     *
     * @template T of array<string, mixed>
     * @param T $baseArray Base payload array
     * @return T&array{icons?: list<array<string, mixed>>}
     */
    protected function mergeIcons(array $baseArray): array
    {
        $icons = $this->resolvedIcons();

        if ($icons === []) {
            return $baseArray;
        }

        return [...$baseArray, 'icons' => array_map(fn(Icon $icon): array => $icon->toArray(), $icons)];
    }

    /**
     * Determine whether the primitive should be registered.
     *
     * @return bool
     */
    public function eligibleForRegistration(): bool
    {
        if (method_exists($this, 'shouldRegister')) {
            $invoker = ContainerRegistry::getInstance()->get(ContainerInvoker::class);

            return (bool)$invoker->call([$this, 'shouldRegister']);
        }

        return true;
    }

    /**
     * Get the JSON-RPC method call payload for the primitive.
     *
     * @return array<string, mixed>
     */
    abstract public function toMethodCall(): array;

    /**
     * Convert the primitive to an array.
     *
     * @return array<string, mixed>
     */
    abstract public function toArray(): array;
}
