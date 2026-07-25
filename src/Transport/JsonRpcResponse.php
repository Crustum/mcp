<?php
declare(strict_types=1);

namespace Crustum\Mcp\Transport;

use Crustum\Mcp\Contracts\Arrayable;

/**
 * JSON-RPC response message.
 *
 * @implements \Crustum\Mcp\Contracts\Arrayable<string, mixed>
 */
class JsonRpcResponse implements Arrayable
{
    /**
     * Create a new JSON-RPC response.
     *
     * @param array<string, mixed> $content Response payload
     */
    public function __construct(protected array $content = [])
    {
    }

    /**
     * Create a successful JSON-RPC result response.
     *
     * @param string|int $id Request identifier
     * @param array<string, mixed> $result Result payload
     * @return self
     */
    public static function result(int|string $id, array $result): self
    {
        return new self([
            'id' => $id,
            'result' => $result === [] ? (object)[] : $result,
        ]);
    }

    /**
     * Create a JSON-RPC notification response.
     *
     * @param string $method Notification method name
     * @param array<string, mixed> $params Notification parameters
     * @return self
     */
    public static function notification(string $method, array $params): self
    {
        return new self([
            'method' => $method,
            'params' => $params === [] ? (object)[] : $params,
        ]);
    }

    /**
     * Create a JSON-RPC error response.
     *
     * @param string|int|null $id Request identifier
     * @param int $code JSON-RPC error code
     * @param string $message Error message
     * @param array<string, mixed>|null $data Additional error data
     * @return self
     */
    public static function error(string|int|null $id, int $code, string $message, ?array $data = null): self
    {
        $error = [
            'code' => $code,
            'message' => $message,
        ];

        if ($data !== null) {
            $error['data'] = $data;
        }

        return new self([
            ...$id === null ? [] : ['id' => $id],
            'error' => $error,
        ]);
    }

    /**
     * Get the response as an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'jsonrpc' => '2.0',
            ...$this->content,
        ];
    }

    /**
     * Encode the response as JSON.
     *
     * @param int $options JSON encode options
     * @return string
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options | JSON_UNESCAPED_UNICODE) ?: '';
    }
}
