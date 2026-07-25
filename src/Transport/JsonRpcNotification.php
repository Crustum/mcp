<?php
declare(strict_types=1);

namespace Crustum\Mcp\Transport;

use Crustum\Mcp\Contracts\Arrayable;
use Crustum\Mcp\Exception\JsonRpcException;

/**
 * JSON-RPC notification message.
 *
 * @implements \Crustum\Mcp\Contracts\Arrayable<string, mixed>
 */
class JsonRpcNotification implements Arrayable
{
    /**
     * Create a new JSON-RPC notification.
     *
     * @param string $method Notification method name
     * @param array<string, mixed> $params Notification parameters
     */
    public function __construct(
        public string $method,
        public array $params,
    ) {
    }

    /**
     * Create a notification from a raw JSON-RPC payload.
     *
     * @param array{jsonrpc?: mixed, method?: mixed, params?: array<string, mixed>} $jsonRequest Raw JSON-RPC payload
     * @return self
     * @throws \Crustum\Mcp\Exception\JsonRpcException
     */
    public static function from(array $jsonRequest): self
    {
        if (!isset($jsonRequest['jsonrpc']) || $jsonRequest['jsonrpc'] !== '2.0') {
            throw new JsonRpcException('Invalid Request: Invalid JSON-RPC version. Must be "2.0".', -32600);
        }

        if (!isset($jsonRequest['method']) || !is_string($jsonRequest['method'])) {
            throw new JsonRpcException('Invalid Request: Invalid or missing "method". Must be a string.', -32600);
        }

        return new self(
            method: $jsonRequest['method'],
            params: $jsonRequest['params'] ?? [],
        );
    }

    /**
     * Get the notification as an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'jsonrpc' => '2.0',
            'method' => $this->method,
            ...$this->params === [] ? [] : ['params' => $this->params],
        ];
    }

    /**
     * Encode the notification as JSON.
     *
     * @param int $options JSON encode options
     * @return string
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options | JSON_UNESCAPED_UNICODE) ?: '';
    }
}
