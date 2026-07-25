<?php
declare(strict_types=1);

namespace Crustum\Mcp\Exception;

use Crustum\Mcp\Transport\JsonRpcResponse;
use Exception;

/**
 * JSON-RPC protocol exception.
 */
class JsonRpcException extends Exception
{
    /**
     * @param string $message Error message
     * @param int $code JSON-RPC error code
     * @param mixed $requestId Request identifier
     * @param array<string, mixed>|null $data Additional error data
     */
    public function __construct(
        string $message,
        int $code,
        protected mixed $requestId = null,
        protected ?array $data = null,
    ) {
        parent::__construct($message, $code);
    }

    /**
     * Convert the exception to a JSON-RPC response.
     *
     * @return \Crustum\Mcp\Transport\JsonRpcResponse
     */
    public function toJsonRpcResponse(): JsonRpcResponse
    {
        $id = is_string($this->requestId) || is_int($this->requestId)
            ? $this->requestId
            : null;

        return JsonRpcResponse::error(
            id: $id,
            code: $this->getCode(),
            message: $this->getMessage(),
            data: $this->data,
        );
    }
}
