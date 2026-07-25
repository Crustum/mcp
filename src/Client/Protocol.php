<?php
declare(strict_types=1);

namespace Crustum\Mcp\Client;

use Cake\Utility\Hash;
use Crustum\Mcp\Client\Contracts\Method;
use Crustum\Mcp\Client\Contracts\Transport;
use Crustum\Mcp\Client\Methods\Initialize;
use Crustum\Mcp\Client\Schema\InitializeResult;
use Crustum\Mcp\Exception\ClientException;
use Crustum\Mcp\Exception\JsonRpcException;
use Crustum\Mcp\Exception\SessionExpiredException;
use Crustum\Mcp\Schema\Implementation;
use Crustum\Mcp\Transport\JsonRpcNotification;
use Crustum\Mcp\Transport\JsonRpcRequest;
use Crustum\Mcp\Transport\JsonRpcResponse;
use JsonException;
use Throwable;

/**
 * MCP client JSON-RPC protocol handler.
 */
class Protocol
{
    /**
     * Whether the client is connected and initialized.
     *
     * @var bool
     */
    protected bool $connected = false;

    /**
     * Whether the client is currently connecting.
     *
     * @var bool
     */
    protected bool $connecting = false;

    /**
     * Next JSON-RPC request identifier.
     *
     * @var int
     */
    protected int $nextRequestId = 1;

    /**
     * Initialize handshake result.
     *
     * @var \Crustum\Mcp\Client\Schema\InitializeResult|null
     */
    protected ?InitializeResult $initializeResult = null;

    /**
     * Create a new MCP client protocol handler.
     *
     * @param \Crustum\Mcp\Client\Contracts\Transport $transport Client transport
     * @param \Crustum\Mcp\Schema\Implementation $clientInfo Client implementation metadata
     */
    public function __construct(
        protected Transport $transport,
        protected Implementation $clientInfo,
    ) {
    }

    /**
     * Determine whether the client is connected.
     *
     * @return bool
     */
    public function connected(): bool
    {
        return $this->connected;
    }

    /**
     * Get the initialize handshake result.
     *
     * @return \Crustum\Mcp\Client\Schema\InitializeResult|null
     */
    public function initializeResult(): ?InitializeResult
    {
        return $this->initializeResult;
    }

    /**
     * Connect and initialize the MCP session.
     *
     * @return void
     */
    public function connect(): void
    {
        if ($this->connected) {
            return;
        }

        $this->transport->connect();
        $this->connecting = true;

        try {
            $this->initializeResult = (new Initialize($this->clientInfo))->handle($this);

            $this->transport->setProtocolVersion($this->initializeResult->protocolVersion);

            $this->notify('notifications/initialized');
        } catch (Throwable $throwable) {
            $this->disconnect();

            throw $throwable;
        } finally {
            $this->connecting = false;
        }

        $this->connected = true;
    }

    /**
     * Disconnect from the MCP server.
     *
     * @return void
     */
    public function disconnect(): void
    {
        $this->connected = false;

        $this->transport->disconnect();
    }

    /**
     * Dispatch a JSON-RPC method over the transport.
     *
     * @param \Crustum\Mcp\Client\Contracts\Method<mixed> $method Method to dispatch
     * @return array<string, mixed>
     */
    public function dispatch(Method $method): array
    {
        if (!$this->connected && !$this->connecting) {
            $this->connect();
        }

        try {
            return $this->attempt($method);
        } catch (SessionExpiredException) {
            $this->connect();

            return $this->attempt($method);
        }
    }

    /**
     * Attempt a single JSON-RPC request/response exchange.
     *
     * @param \Crustum\Mcp\Client\Contracts\Method<mixed> $method Method to dispatch
     * @return array<string, mixed>
     */
    protected function attempt(Method $method): array
    {
        $request = new JsonRpcRequest(
            id: $this->nextRequestId++,
            method: $method->method(),
            params: $method->params(),
        );

        try {
            $this->transport->send($request->toJson());

            do {
                $raw = $this->transport->receive();

                try {
                    $response = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
                } catch (JsonException $jsonException) {
                    throw new ClientException(
                        'Malformed JSON-RPC response from server: ' . $jsonException->getMessage(),
                        0,
                        $jsonException,
                    );
                }

                if (!is_array($response) || Hash::get($response, 'jsonrpc') !== '2.0') {
                    throw new ClientException('Invalid JSON-RPC response from server.');
                }

                $this->handleServerRequest($response);
            } while (Hash::get($response, 'id') !== $request->id);

            $hasResult = array_key_exists('result', $response);
            $hasError = array_key_exists('error', $response);
            $error = Hash::get($response, 'error');

            if ($hasResult === $hasError) {
                throw new ClientException('Invalid JSON-RPC response: must contain exactly one of "result" or "error".');
            }

            if ($hasError && !is_array($error)) {
                throw new ClientException('Invalid JSON-RPC error payload.');
            }
        } catch (Throwable $throwable) {
            if ($this->connected) {
                $this->disconnect();
            }

            throw $throwable;
        }

        if ($hasError) {
            $message = Hash::get($error, 'message', 'Unknown JSON-RPC error.');
            $code = Hash::get($error, 'code', 0);
            $data = Hash::get($error, 'data');

            throw new JsonRpcException(
                is_string($message) ? $message : 'Unknown JSON-RPC error.',
                is_int($code) ? $code : 0,
                Hash::get($response, 'id'),
                is_array($data) ? $data : null,
            );
        }

        $result = Hash::get($response, 'result');

        return is_array($result) ? $result : [];
    }

    /**
     * Send a JSON-RPC notification.
     *
     * @param string $method Notification method name
     * @return void
     */
    public function notify(string $method): void
    {
        $notification = new JsonRpcNotification($method, []);

        $this->transport->send($notification->toJson());
    }

    /**
     * Handle server-initiated JSON-RPC requests in the response stream.
     *
     * @param array<string, mixed> $frame JSON-RPC frame
     * @return void
     */
    protected function handleServerRequest(array $frame): void
    {
        $id = Hash::get($frame, 'id');
        $method = Hash::get($frame, 'method');

        if (!is_string($method) || (!is_int($id) && !is_string($id))) {
            return;
        }

        if ($method === 'ping') {
            $this->transport->send(JsonRpcResponse::result($id, [])->toJson());

            return;
        }

        $this->transport->send(JsonRpcResponse::error(
            $id,
            -32601,
            "Method [{$method}] not supported by this client.",
        )->toJson());
    }
}
