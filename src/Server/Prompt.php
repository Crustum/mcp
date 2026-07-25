<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server;

use Crustum\Mcp\Server\Prompts\Argument;

/**
 * Base MCP prompt primitive.
 */
abstract class Prompt extends Primitive
{
    /**
     * Get prompt argument definitions.
     *
     * @return array<int, \Crustum\Mcp\Server\Prompts\Argument>
     */
    public function arguments(): array
    {
        return [];
    }

    /**
     * Get the JSON-RPC method call payload for the prompt.
     *
     * @return array<string, mixed>
     */
    public function toMethodCall(): array
    {
        return ['name' => $this->name()];
    }

    /**
     * Convert the prompt to an array.
     *
     * @return array{
     *     name: string,
     *     title: string,
     *     description: string,
     *     arguments: array<int, array{name: string, description: string, required: bool, _meta?: array<string, mixed>}>
     * }
     */
    public function toArray(): array
    {
        return $this->mergeMeta($this->mergeIcons([
            'name' => $this->name(),
            'title' => $this->title(),
            'description' => $this->description(),
            'arguments' => array_map(
                fn(Argument $argument): array => $argument->toArray(),
                $this->arguments(),
            ),
        ]));
    }
}
