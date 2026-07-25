<?php
declare(strict_types=1);

namespace Crustum\Mcp\Transport;

use Crustum\Mcp\Contracts\Arrayable;
use Crustum\Mcp\Exception\JsonRpcException;
use Crustum\Mcp\Request;

/**
 * JSON-RPC request message.
 *
 * @implements \Crustum\Mcp\Contracts\Arrayable<string, mixed>
 */
class JsonRpcRequest implements Arrayable
{
    /**
     * Create a new JSON-RPC request.
     *
     * @param string|int $id Request identifier
     * @param string $method Request method name
     * @param array<string, mixed> $params Request parameters
     * @param string|null $sessionId MCP session identifier
     */
    public function __construct(
        public int|string $id,
        public string $method,
        public array $params,
        public ?string $sessionId = null,
    ) {
    }

    /**
     * Create a request from a raw JSON-RPC payload.
     *
     * @param array{id: mixed, jsonrpc?: mixed, method?: mixed, params?: mixed} $jsonRequest Raw JSON-RPC payload
     * @param string|null $sessionId MCP session identifier
     * @return self
     * @throws \Crustum\Mcp\Exception\JsonRpcException
     */
    public static function from(array $jsonRequest, ?string $sessionId = null): self
    {
        $requestId = $jsonRequest['id'];

        if (!is_int($jsonRequest['id']) && !is_string($jsonRequest['id'])) {
            throw new JsonRpcException('Invalid Request: The [id] member must be a string, number.', -32600, $requestId);
        }

        if (!isset($jsonRequest['jsonrpc']) || $jsonRequest['jsonrpc'] !== '2.0') {
            throw new JsonRpcException('Invalid Request: The [jsonrpc] member must be exactly [2.0].', -32600, $requestId);
        }

        if (!isset($jsonRequest['method']) || !is_string($jsonRequest['method'])) {
            throw new JsonRpcException('Invalid Request: The [method] member is required and must be a string.', -32600, $requestId);
        }

        if (array_key_exists('params', $jsonRequest) && !self::isObject($jsonRequest['params'])) {
            throw new JsonRpcException('Invalid params: The [params] member must be an object.', -32602, $requestId);
        }

        return new self(
            id: $requestId,
            method: $jsonRequest['method'],
            params: $jsonRequest['params'] ?? [],
            sessionId: $sessionId,
        );
    }

    /**
     * Whether the value is a JSON object (associative or empty array).
     *
     * @param mixed $value Candidate value
     * @return bool
     */
    private static function isObject(mixed $value): bool
    {
        return is_array($value) && ($value === [] || !array_is_list($value));
    }

    /**
     * Get the pagination cursor from request parameters.
     *
     * @return string|null
     */
    public function cursor(): ?string
    {
        $cursor = $this->get('cursor');

        return is_string($cursor) ? $cursor : null;
    }

    /**
     * Get a request parameter value.
     *
     * @param string $key Parameter key
     * @param mixed $default Default value
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    /**
     * Get request metadata from parameters.
     *
     * @return array<string, mixed>|null
     */
    public function meta(): ?array
    {
        return isset($this->params['_meta']) && is_array($this->params['_meta']) ? $this->params['_meta'] : null;
    }

    /**
     * Convert the JSON-RPC request to an MCP request object.
     *
     * @return \Crustum\Mcp\Request
     * @throws \Crustum\Mcp\Exception\JsonRpcException
     */
    public function toRequest(): Request
    {
        if (array_key_exists('arguments', $this->params)) {
            $arguments = $this->params['arguments'];

            if (!self::isObject($arguments)) {
                throw new JsonRpcException('Invalid params: The [arguments] member must be an object.', -32602, $this->id);
            }
        } else {
            $arguments = [];
        }

        return new Request($arguments, $this->sessionId, $this->meta());
    }

    /**
     * Get the request as an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $this->id,
            'method' => $this->method,
            ...$this->params === [] ? [] : ['params' => $this->params],
        ];
    }

    /**
     * Encode the request as JSON.
     *
     * @param int $options JSON encode options
     * @return string
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options | JSON_UNESCAPED_UNICODE) ?: '';
    }
}
