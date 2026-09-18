<?php
declare(strict_types=1);

namespace Crustum\Mcp\Transport;

use Crustum\Mcp\Contracts\Arrayable;
use Crustum\Mcp\Enums\MetaKey;
use Crustum\Mcp\Enums\RequestHeader;
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
     */
    public function __construct(
        public int|string $id,
        public string $method,
        public array $params,
    ) {
    }

    /**
     * Create a request from a raw JSON-RPC payload.
     *
     * @param array{id: mixed, jsonrpc?: mixed, method?: mixed, params?: mixed} $jsonRequest Raw JSON-RPC payload
     * @return self
     * @throws \Crustum\Mcp\Exception\JsonRpcException
     */
    public static function from(array $jsonRequest): self
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
        return isset($this->params['_meta']) && self::isObject($this->params['_meta']) ? $this->params['_meta'] : null;
    }

    /**
     * Whether the request is a legacy client message without protocol metadata.
     *
     * A request carrying either the protocol version or the client capabilities
     * member in its `_meta` is treated as a modern protocol request.
     *
     * @return bool
     */
    public function isLegacy(): bool
    {
        $meta = $this->meta() ?? [];

        return !array_key_exists(MetaKey::PROTOCOL_VERSION->value, $meta)
            && !array_key_exists(MetaKey::CLIENT_CAPABILITIES->value, $meta);
    }

    /**
     * Get the headers this request mirrors onto the HTTP transport.
     *
     * @return array<string, string>
     */
    public function mirroredHeaders(): array
    {
        $headers = [RequestHeader::METHOD->value => $this->method];
        $name = $this->name();

        if ($name !== null) {
            $headers[RequestHeader::NAME->value] = (string)new HeaderValue($name);
        }

        return $headers;
    }

    /**
     * Get the named target of the request when it references one.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        $key = $this->nameKey();

        if ($key === null) {
            return null;
        }

        $name = $this->get($key);

        return is_string($name) ? $name : null;
    }

    /**
     * Whether the request references a named target that must be mirrored.
     *
     * @return bool
     */
    public function requiresName(): bool
    {
        return $this->nameKey() !== null;
    }

    /**
     * Get the parameter key that names this request's target.
     *
     * @return string|null
     */
    private function nameKey(): ?string
    {
        return match ($this->method) {
            'tools/call', 'prompts/get' => 'name',
            'resources/read' => 'uri',
            default => null,
        };
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

        return new Request($arguments, $this->meta());
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
