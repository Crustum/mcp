<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Prompts;

use Crustum\Mcp\Contracts\Arrayable;

/**
 * MCP prompt argument definition.
 *
 * @implements \Crustum\Mcp\Contracts\Arrayable<string, mixed>
 */
class Argument implements Arrayable
{
    /**
     * @param string $name Argument name
     * @param string $description Argument description
     * @param bool $required Whether the argument is required
     */
    public function __construct(
        public string $name,
        public string $description,
        public bool $required = false,
    ) {
    }

    /**
     * Convert the argument to an array.
     *
     * @return array{name: string, description: string, required: bool}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'required' => $this->required,
        ];
    }
}
