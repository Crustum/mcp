<?php
declare(strict_types=1);

namespace Crustum\Mcp\Client\Primitives;

use Cake\Utility\Hash;
use Crustum\Mcp\Client;
use Crustum\Mcp\Client\Schema\ToolResult;
use Crustum\Mcp\Exception\ClientException;

/**
 * MCP tool primitive bound to a client instance.
 */
class Tool
{
    /**
     * Create a new tool primitive.
     *
     * @param \Crustum\Mcp\Client|null $client Bound MCP client
     * @param string $name Tool name
     * @param string|null $title Tool title
     * @param string|null $description Tool description
     * @param array<string, mixed> $inputSchema Tool input JSON schema
     * @param array<string, mixed>|null $outputSchema Tool output JSON schema
     * @param array<string, mixed> $annotations Tool annotations
     * @param array<string, mixed>|null $meta Tool metadata
     */
    public function __construct(
        protected ?Client $client,
        public readonly string $name,
        public readonly ?string $title,
        public readonly ?string $description,
        public readonly array $inputSchema,
        public readonly ?array $outputSchema,
        public readonly array $annotations,
        public readonly ?array $meta,
    ) {
    }

    /**
     * Create a tool primitive from a server payload.
     *
     * @param \Crustum\Mcp\Client|null $client Bound MCP client
     * @param array<string, mixed> $payload Tool list payload
     * @return self
     */
    public static function from(?Client $client, array $payload): self
    {
        $name = Hash::get($payload, 'name');
        $title = Hash::get($payload, 'title');
        $description = Hash::get($payload, 'description');
        $inputSchema = Hash::get($payload, 'inputSchema', []);
        $outputSchema = Hash::get($payload, 'outputSchema');
        $annotations = Hash::get($payload, 'annotations', []);
        $meta = Hash::get($payload, '_meta');

        if (
            !is_string($name)
            || trim($name) === ''
            || !is_array($inputSchema)
            || !is_array($annotations)
            || ($title !== null && !is_string($title))
            || ($description !== null && !is_string($description))
            || ($outputSchema !== null && !is_array($outputSchema))
            || ($meta !== null && !is_array($meta))
        ) {
            throw new ClientException('Invalid tool payload from server.');
        }

        return new self(
            client: $client,
            name: $name,
            title: $title,
            description: $description,
            inputSchema: $inputSchema,
            outputSchema: $outputSchema,
            annotations: $annotations,
            meta: $meta,
        );
    }

    /**
     * Execute the tool on the bound client.
     *
     * @param array<string, mixed> $arguments Tool arguments
     * @return \Crustum\Mcp\Client\Schema\ToolResult
     */
    public function call(array $arguments = []): ToolResult
    {
        if (!$this->client instanceof Client) {
            throw new ClientException("Tool [{$this->name}] is not bound to a client.");
        }

        return $this->client->callTool($this->name, $arguments);
    }
}
