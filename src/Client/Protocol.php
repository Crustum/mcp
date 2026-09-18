<?php
declare(strict_types=1);

namespace Crustum\Mcp\Client;

use Cake\Utility\Hash;
use Crustum\Mcp\Client\Contracts\Method;
use Crustum\Mcp\Client\Contracts\MirrorsParameters;
use Crustum\Mcp\Client\Contracts\Transport;
use Crustum\Mcp\Client\Contracts\UsesProtocol;
use Crustum\Mcp\Client\Exception\OAuthException;
use Crustum\Mcp\Client\Exception\TransportException;
use Crustum\Mcp\Client\Methods\Discover;
use Crustum\Mcp\Client\Methods\Initialize;
use Crustum\Mcp\Client\Schema\DiscoverResult;
use Crustum\Mcp\Client\Schema\InitializeResult;
use Crustum\Mcp\Enums\ErrorCode;
use Crustum\Mcp\Enums\MetaKey;
use Crustum\Mcp\Enums\ProtocolHandshake;
use Crustum\Mcp\Enums\ProtocolVersion;
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
     * Negotiated connection state.
     *
     * @var \Crustum\Mcp\Client\NegotiatedConnection|null
     */
    protected ?NegotiatedConnection $connection = null;

    protected ?ResponseCache $cache = null;

    /**
     * Create a new MCP client protocol handler.
     *
     * @param \Crustum\Mcp\Client\Contracts\Transport $transport Client transport
     * @param \Crustum\Mcp\Schema\Implementation $clientInfo Client implementation metadata
     * @param \Crustum\Mcp\Enums\ProtocolVersion|null $pinnedProtocolVersion Pinned protocol version
     */
    public function __construct(
        protected Transport $transport,
        protected Implementation $clientInfo,
        protected ?ProtocolVersion $pinnedProtocolVersion = null,
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
        return $this->connection?->initializeResult();
    }

    /**
     * Get the discover handshake result.
     *
     * @return \Crustum\Mcp\Client\Schema\DiscoverResult|null
     */
    public function discoverResult(): ?DiscoverResult
    {
        return $this->connection?->discoverResult();
    }

    /**
     * Get the negotiated server capabilities.
     *
     * @return array<string, mixed>
     */
    public function capabilities(): array
    {
        return $this->connection?->capabilities() ?? [];
    }

    /**
     * Get the negotiated server implementation.
     *
     * @return \Crustum\Mcp\Schema\Implementation|null
     */
    public function serverInfo(): ?Implementation
    {
        return $this->connection?->serverInfo();
    }

    /**
     * Get the negotiated server instructions.
     *
     * @return string|null
     */
    public function instructions(): ?string
    {
        return $this->connection?->instructions();
    }

    /**
     * Pin the protocol version used for future negotiation.
     *
     * @param \Crustum\Mcp\Enums\ProtocolVersion|null $protocolVersion Protocol version to pin
     * @return void
     */
    public function pinProtocolVersion(?ProtocolVersion $protocolVersion): void
    {
        $this->pinnedProtocolVersion = $protocolVersion;
        $this->connection = null;

        if ($this->connected) {
            $this->disconnect();
        }
    }

    /**
     * Get the pinned protocol version.
     *
     * @return \Crustum\Mcp\Enums\ProtocolVersion|null
     */
    public function pinnedProtocolVersion(): ?ProtocolVersion
    {
        return $this->pinnedProtocolVersion;
    }

    /**
     * Connect and negotiate the MCP session.
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
            $this->handshake();
        } catch (Throwable $throwable) {
            $this->disconnect();

            throw $throwable;
        } finally {
            $this->connecting = false;
        }

        $this->connected = true;
    }

    /**
     * Negotiate a connection using the pinned or remembered era.
     *
     * @return void
     */
    protected function handshake(): void
    {
        $pinned = $this->pinnedProtocolVersion;

        if ($pinned instanceof ProtocolVersion) {
            $this->connection = $pinned->handshake() === ProtocolHandshake::Discovery
                ? $this->discover($pinned)
                : $this->initialize($pinned, true);

            return;
        }

        $remembered = $this->connection?->protocolVersion;

        if ($remembered?->handshake() === ProtocolHandshake::Initialize) {
            try {
                $this->connection = $this->initialize($remembered);

                return;
            } catch (OAuthException $oAuthException) {
                throw $oAuthException;
            } catch (Throwable) {
                $this->connection = null;

                $this->transport->connect();
            }
        }

        $this->connection = $this->probe();
    }

    /**
     * Probe the modern era first and fall back to the legacy handshake.
     *
     * @return \Crustum\Mcp\Client\NegotiatedConnection
     */
    protected function probe(): NegotiatedConnection
    {
        try {
            return $this->discover();
        } catch (JsonRpcException $jsonRpcException) {
            if ($this->identifiesModernServer($jsonRpcException)) {
                return $this->retryWithMutualVersion($jsonRpcException);
            }

            if (!$this->identifiesLegacyServer($jsonRpcException)) {
                throw $jsonRpcException;
            }

            $rejection = null;
        } catch (TransportException $transportException) {
            $rejection = $transportException;
        }

        try {
            $this->transport->connect();

            return $this->initialize(ProtocolVersion::V2025_11_25);
        } catch (OAuthException $oAuthException) {
            throw $oAuthException;
        } catch (Throwable $throwable) {
            throw $rejection instanceof ClientException
                ? new ClientException(sprintf(
                    '%s The legacy handshake also failed: %s',
                    $rejection->getMessage(),
                    $throwable->getMessage(),
                ), 0, $throwable)
                : $throwable;
        }
    }

    /**
     * Run the legacy initialize handshake for a protocol version.
     *
     * @param \Crustum\Mcp\Enums\ProtocolVersion $protocolVersion Protocol version to offer
     * @param bool $pinned Whether the version was pinned by the caller
     * @return \Crustum\Mcp\Client\NegotiatedConnection
     */
    protected function initialize(ProtocolVersion $protocolVersion, bool $pinned = false): NegotiatedConnection
    {
        $result = InitializeResult::from($this->attempt(
            new Initialize($this->clientInfo, $protocolVersion),
            $protocolVersion,
        ));
        $settled = ProtocolVersion::from($result->protocolVersion);

        if ($pinned && $settled !== $protocolVersion) {
            throw $this->versionMismatch($settled, $protocolVersion);
        }

        $this->notify('notifications/initialized', $settled);

        return new NegotiatedConnection($settled, $result);
    }

    /**
     * Run the modern discovery handshake.
     *
     * @param \Crustum\Mcp\Enums\ProtocolVersion|null $pinned Pinned protocol version
     * @return \Crustum\Mcp\Client\NegotiatedConnection
     */
    protected function discover(?ProtocolVersion $pinned = null): NegotiatedConnection
    {
        $offered = $pinned ?? ProtocolVersion::LATEST;
        $result = DiscoverResult::from($this->attempt(new Discover(), $offered));
        $settled = ProtocolVersion::preferredFrom(...$result->supportedVersions);

        if (!$settled instanceof ProtocolVersion) {
            throw new ClientException(sprintf(
                'The server supports protocol versions [%s]. This client supports [%s].',
                implode(', ', $result->supportedVersions),
                implode(', ', ProtocolVersion::clientSupported()),
            ));
        }

        if ($pinned instanceof ProtocolVersion && $settled !== $pinned) {
            throw $this->versionMismatch($settled, $pinned);
        }

        return $settled->handshake() === ProtocolHandshake::Initialize
            ? $this->initialize($settled)
            : new NegotiatedConnection($settled, $result);
    }

    /**
     * Retry the legacy handshake using a mutual version from an error payload.
     *
     * @param \Crustum\Mcp\Exception\JsonRpcException $jsonRpcException Protocol error
     * @return \Crustum\Mcp\Client\NegotiatedConnection
     */
    protected function retryWithMutualVersion(JsonRpcException $jsonRpcException): NegotiatedConnection
    {
        $supported = Hash::get($jsonRpcException->data() ?? [], 'supported');

        $protocolVersion = is_array($supported)
            ? ProtocolVersion::preferredFrom(...array_values(array_filter($supported, is_string(...))))
            : null;

        if (!$protocolVersion instanceof ProtocolVersion || $protocolVersion->handshake() !== ProtocolHandshake::Initialize) {
            throw $jsonRpcException;
        }

        return $this->initialize($protocolVersion);
    }

    /**
     * Build the error thrown when the server settles on a different version.
     *
     * @param \Crustum\Mcp\Enums\ProtocolVersion $settled Version the server settled on
     * @param \Crustum\Mcp\Enums\ProtocolVersion $pinned Version that was requested
     * @return \Crustum\Mcp\Exception\ClientException
     */
    protected function versionMismatch(ProtocolVersion $settled, ProtocolVersion $pinned): ClientException
    {
        return new ClientException(sprintf(
            'The server settled on protocol version [%s] while [%s] was requested.',
            $settled->value,
            $pinned->value,
        ));
    }

    /**
     * Determine whether an error identifies a modern server.
     *
     * @param \Crustum\Mcp\Exception\JsonRpcException $jsonRpcException Protocol error
     * @return bool
     */
    protected function identifiesModernServer(JsonRpcException $jsonRpcException): bool
    {
        return in_array($jsonRpcException->getCode(), [
            ErrorCode::HEADER_MISMATCH->value,
            ErrorCode::MISSING_REQUIRED_CLIENT_CAPABILITY->value,
            ErrorCode::UNSUPPORTED_PROTOCOL_VERSION->value,
        ], true);
    }

    /**
     * Determine whether an error identifies a legacy server.
     *
     * @param \Crustum\Mcp\Exception\JsonRpcException $jsonRpcException Protocol error
     * @return bool
     */
    protected function identifiesLegacyServer(JsonRpcException $jsonRpcException): bool
    {
        $code = $jsonRpcException->getCode();

        return in_array($code, [
            ErrorCode::PARSE_ERROR->value,
            ErrorCode::INVALID_REQUEST->value,
            ErrorCode::METHOD_NOT_FOUND->value,
        ], true) || ($code <= -32000 && $code >= -32099);
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
        if (!$this->cache instanceof ResponseCache) {
            return $this->roundTrip($method);
        }

        return $this->cache->remember(
            $method,
            $this->transport,
            fn(): array => $this->roundTrip($method),
        );
    }

    /**
     * Set or clear the response cache.
     *
     * @param \Crustum\Mcp\Client\ResponseCache|null $responseCache Cache instance or null to disable
     * @return void
     */
    public function useCache(?ResponseCache $responseCache): void
    {
        $this->cache = $responseCache;
    }

    /**
     * Get the current response cache.
     *
     * @return \Crustum\Mcp\Client\ResponseCache|null
     */
    public function cache(): ?ResponseCache
    {
        return $this->cache;
    }

    /**
     * Execute a JSON-RPC round trip without caching.
     *
     * @param \Crustum\Mcp\Client\Contracts\Method<mixed> $method Method to dispatch
     * @return array<string, mixed>
     */
    protected function roundTrip(Method $method): array
    {
        if (!$this->connected && !$this->connecting) {
            $this->connect();
        }

        try {
            return $this->attempt($method, $this->connectionProtocol());
        } catch (SessionExpiredException) {
            $this->connect();

            return $this->attempt($method, $this->connectionProtocol());
        }
    }

    /**
     * Attempt a single JSON-RPC request/response exchange.
     *
     * @param \Crustum\Mcp\Client\Contracts\Method<mixed> $method Method to dispatch
     * @param \Crustum\Mcp\Enums\ProtocolVersion $protocolVersion Protocol version for the exchange
     * @return array<string, mixed>
     */
    protected function attempt(Method $method, ProtocolVersion $protocolVersion): array
    {
        $this->configureTransport($protocolVersion);

        $request = new JsonRpcRequest(
            id: $this->nextRequestId++,
            method: $method->method(),
            params: $this->params($method, $protocolVersion),
        );

        try {
            $this->transport->send(
                $request->toJson(),
                $this->requestHeaders($method, $request, $protocolVersion),
            );

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

                $responseId = Hash::get($response, 'id');
            } while ($responseId !== $request->id && ($responseId !== null || !array_key_exists('error', $response)));

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
     * Resolve the request headers for a method and protocol era.
     *
     * @param \Crustum\Mcp\Client\Contracts\Method<mixed> $method Method to dispatch
     * @param \Crustum\Mcp\Transport\JsonRpcRequest $request JSON-RPC request
     * @param \Crustum\Mcp\Enums\ProtocolVersion $protocolVersion Protocol version for the exchange
     * @return array<string, string>
     */
    protected function requestHeaders(Method $method, JsonRpcRequest $request, ProtocolVersion $protocolVersion): array
    {
        if ($protocolVersion->handshake() !== ProtocolHandshake::Discovery) {
            return [];
        }

        return [
            ...$request->mirroredHeaders(),
            ...$method instanceof MirrorsParameters ? $method->requestHeaders() : [],
        ];
    }

    /**
     * Resolve the request params for a method and protocol era.
     *
     * @param \Crustum\Mcp\Client\Contracts\Method<mixed> $method Method to dispatch
     * @param \Crustum\Mcp\Enums\ProtocolVersion $protocolVersion Protocol version for the exchange
     * @return array<string, mixed>
     */
    protected function params(Method $method, ProtocolVersion $protocolVersion): array
    {
        $params = $method->params();

        if ($protocolVersion->handshake() !== ProtocolHandshake::Discovery) {
            return $params;
        }

        $meta = Hash::get($params, '_meta');
        $params['_meta'] = [
            MetaKey::PROTOCOL_VERSION->value => $protocolVersion->value,
            MetaKey::CLIENT_CAPABILITIES->value => (object)[],
            MetaKey::CLIENT_INFO->value => $this->clientInfo->toArray(),
            ...(is_array($meta) ? $meta : []),
        ];

        return $params;
    }

    /**
     * Send a JSON-RPC notification.
     *
     * @param string $method Notification method name
     * @param \Crustum\Mcp\Enums\ProtocolVersion|null $protocolVersion Protocol version to use
     * @return void
     */
    public function notify(string $method, ?ProtocolVersion $protocolVersion = null): void
    {
        $this->configureTransport($protocolVersion ?? $this->connectionProtocol());

        $notification = new JsonRpcNotification($method, []);

        $this->transport->send($notification->toJson());
    }

    /**
     * Get the protocol version of the negotiated connection.
     *
     * @return \Crustum\Mcp\Enums\ProtocolVersion
     */
    public function connectionProtocol(): ProtocolVersion
    {
        if (!$this->connection instanceof NegotiatedConnection) {
            throw new ClientException('The client has not negotiated a protocol version.');
        }

        return $this->connection->protocolVersion;
    }

    /**
     * Configure the transport for the active protocol version.
     *
     * @param \Crustum\Mcp\Enums\ProtocolVersion $protocolVersion Protocol version to use
     * @return void
     */
    protected function configureTransport(ProtocolVersion $protocolVersion): void
    {
        if ($this->transport instanceof UsesProtocol) {
            $this->transport->useProtocol($protocolVersion);
        }
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
