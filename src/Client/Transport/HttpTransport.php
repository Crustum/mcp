<?php
declare(strict_types=1);

namespace Crustum\Mcp\Client\Transport;

use Cake\Http\Client;
use Cake\Http\Client\Exception\NetworkException;
use Cake\Http\Client\Response as ClientResponse;
use Cake\Utility\Hash;
use Closure;
use Crustum\Mcp\Client\Contracts\Transport;
use Crustum\Mcp\Client\Contracts\UsesProtocol;
use Crustum\Mcp\Client\Exception\AuthorizationRequiredException;
use Crustum\Mcp\Client\Exception\TransportException;
use Crustum\Mcp\Client\OAuth\WwwAuthenticateChallenge;
use Crustum\Mcp\Enums\ProtocolHandshake;
use Crustum\Mcp\Enums\ProtocolVersion;
use Crustum\Mcp\Enums\RequestHeader;
use Crustum\Mcp\Exception\ClientException;
use Crustum\Mcp\Exception\SessionExpiredException;
use Psr\Http\Message\StreamInterface;
use SensitiveParameter;
use Throwable;

/**
 * MCP client transport over HTTP with optional SSE responses.
 */
class HttpTransport implements Transport, UsesProtocol
{
    /**
     * Bearer token or token resolver.
     *
     * @var \Closure(): string|string|null
     */
    protected string|Closure|null $token = null;

    /**
     * Active protocol version for the transport.
     *
     * @var \Crustum\Mcp\Enums\ProtocolVersion|null
     */
    protected ?ProtocolVersion $protocolVersion = null;

    /**
     * Active MCP session identifier.
     *
     * @var string|null
     */
    protected ?string $sessionId = null;

    /**
     * Whether the transport has completed initialization.
     *
     * @var bool
     */
    protected bool $initialized = false;

    /**
     * HTTP request timeout in seconds.
     *
     * @var float
     */
    protected float $timeoutSeconds = 30.0;

    /**
     * Custom HTTP headers.
     *
     * @var array<string, string>
     */
    protected array $customHeaders = [];

    /**
     * Queued inbound messages.
     *
     * @var array<int, string>
     */
    protected array $queue = [];

    /**
     * HTTP client instance.
     *
     * @var \Cake\Http\Client|null
     */
    protected ?Client $httpClient = null;

    /**
     * Create a new HTTP transport.
     *
     * @param string $url MCP server endpoint URL
     * @param \Cake\Http\Client|null $httpClient Optional HTTP client for testing
     */
    public function __construct(
        protected string $url,
        ?Client $httpClient = null,
    ) {
        $this->httpClient = $httpClient;
    }

    /**
     * @inheritDoc
     */
    public function connect(): void
    {
        $this->reset();
    }

    /**
     * @inheritDoc
     */
    public function disconnect(): void
    {
        $this->terminateSession();

        $this->reset();
    }

    /**
     * @inheritDoc
     */
    public function setTimeoutSeconds(float $seconds): void
    {
        $this->timeoutSeconds = $seconds;
    }

    /**
     * Configure a bearer token for authenticated requests.
     *
     * @param \Closure(): string|string $token Bearer token or token resolver
     * @return void
     */
    public function withToken(#[SensitiveParameter]
    string|Closure $token,): void
    {
        $this->token = $token;
    }

    /**
     * Merge custom HTTP headers into outbound requests.
     *
     * @param array<string, string> $headers HTTP headers
     * @return void
     */
    public function withHeaders(array $headers): void
    {
        $this->customHeaders = array_merge($this->customHeaders, $headers);
    }

    /**
     * Get the configured MCP server URL.
     *
     * @return string
     */
    public function url(): string
    {
        return $this->url;
    }

    /**
     * @inheritDoc
     */
    public function recipe(): array
    {
        return [
            'driver' => 'http',
            'url' => $this->url,
            'token' => $this->token instanceof Closure ? (string)($this->token)() : $this->token,
            'headers' => $this->customHeaders,
            'timeoutSeconds' => $this->timeoutSeconds,
        ];
    }

    /**
     * @inheritDoc
     */
    public function send(string $message, array $headers = []): void
    {
        $hadSession = $this->sessionId !== null;

        try {
            $response = $this->client()->post($this->url, $message, [
                'headers' => array_merge($this->headers($headers), [
                    'Content-Type' => 'application/json',
                ]),
                'timeout' => $this->timeoutSeconds,
            ]);
        } catch (NetworkException $networkException) {
            $this->failWith("HTTP request to [{$this->url}] failed: {$networkException->getMessage()}");
        }

        $this->captureSessionId($response);

        $statusCode = $response->getStatusCode();

        if ($statusCode === 401 || $statusCode === 403) {
            $challenge = WwwAuthenticateChallenge::parse($response->getHeaderLine('WWW-Authenticate'));

            $this->reset();

            throw new AuthorizationRequiredException(
                "The server responded with HTTP {$statusCode} for endpoint [{$this->url}]. Authorization is required.",
                $challenge,
            );
        }

        if ($statusCode === 404 && $hadSession) {
            $this->reset();

            throw new SessionExpiredException("Session expired. The server responded with HTTP 404 for endpoint [{$this->url}].");
        }

        if (!$response->isSuccess()) {
            $content = trim($response->getStringBody());

            if ($this->hasJsonRpcError($content)) {
                $this->queue[] = $content;

                return;
            }

            if ($statusCode === 404 || ($statusCode >= 500 && $statusCode !== 501)) {
                $this->failWith("Unexpected HTTP status [{$statusCode}] from endpoint [{$this->url}].");
            }

            $this->reset();

            throw new TransportException("The endpoint [{$this->url}] rejected the request with HTTP status [{$statusCode}].");
        }

        if ($this->protocolVersion?->handshake() === ProtocolHandshake::Initialize) {
            $this->initialized = true;
        }

        if (str_contains($response->getHeaderLine('Content-Type'), 'text/event-stream')) {
            $this->readSseStream($response);

            return;
        }

        $content = trim($response->getStringBody());

        if ($statusCode === 202 || $content === '') {
            return;
        }

        $this->queue[] = $content;
    }

    /**
     * Configure the transport for the active protocol version.
     *
     * @param \Crustum\Mcp\Enums\ProtocolVersion $protocolVersion Protocol version
     * @return void
     */
    public function useProtocol(ProtocolVersion $protocolVersion): void
    {
        $previous = $this->protocolVersion;

        if ($previous instanceof ProtocolVersion && $previous->handshake() !== $protocolVersion->handshake()) {
            $this->sessionId = null;
            $this->initialized = false;
        }

        $this->protocolVersion = $protocolVersion;
    }

    /**
     * @inheritDoc
     */
    public function receive(): string
    {
        $message = array_shift($this->queue);

        if ($message === null) {
            throw new ClientException('No message available from the HTTP transport.');
        }

        return $message;
    }

    /**
     * Ensure the MCP session is terminated on teardown.
     */
    public function __destruct()
    {
        $this->disconnect();
    }

    /**
     * Determine whether a response body carries a JSON-RPC error frame.
     *
     * @param string $content Response body
     * @return bool
     */
    protected function hasJsonRpcError(string $content): bool
    {
        $body = json_decode($content, true);

        return is_array($body) && Hash::get($body, 'jsonrpc') === '2.0' && is_array(Hash::get($body, 'error'));
    }

    /**
     * Resolve the HTTP client instance.
     *
     * @return \Cake\Http\Client
     */
    protected function client(): Client
    {
        if ($this->httpClient instanceof Client) {
            return $this->httpClient;
        }

        $this->httpClient = new Client();

        return $this->httpClient;
    }

    /**
     * Build outbound HTTP headers.
     *
     * @param array<string, string> $mirrored Mirrored protocol headers
     * @return array<string, string>
     */
    protected function headers(array $mirrored = []): array
    {
        $headers = [
            'Accept' => 'application/json, text/event-stream',
            ...$this->eraHeaders(),
            ...$mirrored,
        ];

        $token = $this->token instanceof Closure ? (string)($this->token)() : $this->token;

        if ($token !== null && $token !== '') {
            $headers['Authorization'] = "Bearer {$token}";
        }

        foreach ($this->customHeaders as $name => $value) {
            if ($this->reserved($name)) {
                continue;
            }

            foreach (array_keys($headers) as $existing) {
                if (strcasecmp($existing, $name) === 0) {
                    unset($headers[$existing]);
                }
            }

            $headers[$name] = $value;
        }

        return $headers;
    }

    /**
     * Determine whether a header name is reserved by the MCP protocol.
     *
     * @param string $name Header name
     * @return bool
     */
    protected function reserved(string $name): bool
    {
        return str_starts_with(strtolower($name), 'mcp-');
    }

    /**
     * Build the headers specific to the active protocol era.
     *
     * @return array<string, string>
     */
    protected function eraHeaders(): array
    {
        $version = $this->protocolVersion;

        if ($version?->handshake() === ProtocolHandshake::Discovery) {
            return [RequestHeader::PROTOCOL_VERSION->value => $version->value];
        }

        $headers = [];

        if ($this->sessionId !== null) {
            $headers['MCP-Session-Id'] = $this->sessionId;
        }

        if ($this->initialized && $version instanceof ProtocolVersion) {
            $headers[RequestHeader::PROTOCOL_VERSION->value] = $version->value;
        }

        return $headers;
    }

    /**
     * Capture the MCP session identifier from a response.
     *
     * @param \Cake\Http\Client\Response $response HTTP response
     * @return void
     */
    protected function captureSessionId(ClientResponse $response): void
    {
        if ($this->protocolVersion?->handshake() !== ProtocolHandshake::Initialize) {
            return;
        }

        $sessionId = $response->getHeaderLine('MCP-Session-Id');

        if ($sessionId !== '') {
            $this->sessionId = $sessionId;
        }
    }

    /**
     * Read SSE events from an HTTP response body.
     *
     * @param \Cake\Http\Client\Response $response HTTP response
     * @return void
     */
    protected function readSseStream(ClientResponse $response): void
    {
        $stream = $response->getBody();

        while (!$stream->eof()) {
            $line = trim($this->readLine($stream));

            if (str_starts_with($line, 'data:')) {
                $this->queueSseEvent(trim(substr($line, 5)));
            }
        }
    }

    /**
     * Read a single line from a PSR-7 stream.
     *
     * @param \Psr\Http\Message\StreamInterface $stream Response body stream
     * @return string
     */
    protected function readLine(StreamInterface $stream): string
    {
        $line = '';

        while (!$stream->eof()) {
            $byte = $stream->read(1);

            if ($byte === '') {
                break;
            }

            $line .= $byte;

            if ($byte === "\n") {
                break;
            }
        }

        return $line;
    }

    /**
     * Queue a decoded SSE event payload.
     *
     * @param string $data SSE event data
     * @return void
     */
    protected function queueSseEvent(string $data): void
    {
        if ($data === '') {
            return;
        }

        $decoded = json_decode($data, true);

        if (is_array($decoded) && isset($decoded['method'], $decoded['id'])) {
            $this->failWith('The server initiated a request over the SSE stream, which this HTTP client does not support.');
        }

        $this->queue[] = $data;
    }

    /**
     * Terminate the active MCP session when possible.
     *
     * @return void
     */
    protected function terminateSession(): void
    {
        if ($this->sessionId === null) {
            return;
        }

        try {
            $this->client()->delete($this->url, [], [
                'headers' => $this->headers(),
                'timeout' => $this->timeoutSeconds,
            ]);
        } catch (Throwable) {
        }
    }

    /**
     * Reset transport state.
     *
     * @return void
     */
    protected function reset(): void
    {
        $this->protocolVersion = null;
        $this->sessionId = null;
        $this->initialized = false;
        $this->queue = [];
    }

    /**
     * Reset the transport and throw a client exception.
     *
     * @param string $message Failure message
     * @return never
     */
    protected function failWith(string $message): never
    {
        $this->reset();

        throw new ClientException($message);
    }
}
