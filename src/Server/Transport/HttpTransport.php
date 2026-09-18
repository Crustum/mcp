<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Transport;

use Cake\Http\CallbackStream;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Closure;
use Crustum\Mcp\Enums\ErrorCode;
use Crustum\Mcp\Server\Contracts\Transport;
use Psr\Http\Message\ServerRequestInterface;

/**
 * HTTP transport for MCP server requests.
 */
class HttpTransport implements Transport
{
    /**
     * @param \Cake\Http\ServerRequest $request Incoming HTTP request
     * @param (\Closure(string): void)|null $handler Message handler
     * @param string|null $reply Serialized reply payload
     * @param \Closure|null $stream Stream callback
     */
    public function __construct(
        protected ServerRequest $request,
        protected ?Closure $handler = null,
        protected ?string $reply = null,
        protected ?Closure $stream = null,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function onReceive(Closure $handler): void
    {
        $this->handler = $handler;
    }

    /**
     * @inheritDoc
     */
    public function send(string $message): void
    {
        if ($this->stream instanceof Closure) {
            $this->sendStreamMessage($message);
        }

        $this->reply = $message;
    }

    /**
     * @inheritDoc
     */
    public function run(): ?Response
    {
        if (is_callable($this->handler)) {
            ($this->handler)($this->requestBody());
        }

        if ($this->stream instanceof Closure) {
            $stream = $this->stream;

            $response = (new Response())
                ->withStatus(200)
                ->withType('text/event-stream')
                ->withHeader('X-Accel-Buffering', 'no')
                ->withBody(new CallbackStream(function () use ($stream): string {
                    ob_start();

                    $result = $stream();

                    if (is_iterable($result)) {
                        foreach ($result as $message) {
                            if (connection_aborted() !== 0) {
                                break;
                            }

                            echo 'data: ' . $message . "\n\n";
                        }
                    }

                    return (string)ob_get_clean();
                }));

            return $this->applyHeaders($response);
        }

        return $this->applyHeaders(new Response([
            'status' => $this->statusCode(),
            'type' => 'json',
            'body' => $this->reply ?? '',
        ]));
    }

    /**
     * Read the raw JSON-RPC request body.
     *
     * Prefer already-parsed body data: Cake `BodyParserMiddleware` consumes the
     * PSR stream, so `getBody()->getContents()` is often empty on MCP routes.
     *
     * @return string
     */
    protected function requestBody(): string
    {
        $parsed = $this->request->getParsedBody();
        if (is_array($parsed) && $parsed !== []) {
            return json_encode($parsed, JSON_THROW_ON_ERROR);
        }

        $data = $this->request->getData();
        if ($data !== []) {
            return json_encode($data, JSON_THROW_ON_ERROR);
        }

        $stream = $this->request->getBody();
        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        return $stream->getContents();
    }

    /**
     * Resolve the HTTP status code for the current reply.
     *
     * @return int
     */
    protected function statusCode(): int
    {
        // Must be 202 - https://modelcontextprotocol.io/specification/2026-07-28/basic/transports/streamable-http#sending-messages
        if ($this->reply === null) {
            return 202;
        }

        $reply = json_decode($this->reply, true);

        if (!is_array($reply) || !is_array($reply['error'] ?? null)) {
            return 200;
        }

        return match ($reply['error']['code'] ?? null) {
            ErrorCode::METHOD_NOT_FOUND->value => 404,
            ErrorCode::INTERNAL_ERROR->value => 500,
            default => 400,
        };
    }

    /**
     * Apply MCP headers to a response.
     *
     * @param \Cake\Http\Response $response Response instance
     * @return \Cake\Http\Response
     */
    protected function applyHeaders(Response $response): Response
    {
        return $response;
    }

    /**
     * @inheritDoc
     */
    public function stream(Closure $stream): void
    {
        $this->stream = $stream;
    }

    /**
     * Write a single SSE message to the output stream.
     *
     * @param string $message Serialized JSON-RPC message
     * @return void
     */
    protected function sendStreamMessage(string $message): void
    {
        echo 'data: ' . $message . "\n\n";

        if (ob_get_level() !== 0) {
            ob_flush();
        }

        flush();
    }

    /**
     * @inheritDoc
     */
    public function httpRequest(): ?ServerRequestInterface
    {
        return $this->request;
    }
}
