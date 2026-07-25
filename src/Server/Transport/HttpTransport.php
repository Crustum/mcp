<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Transport;

use Cake\Http\CallbackStream;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Closure;
use Crustum\Mcp\Server\Contracts\Transport;
use Psr\Http\Message\ServerRequestInterface;

/**
 * HTTP transport for MCP server requests.
 */
class HttpTransport implements Transport
{
    /**
     * @param \Cake\Http\ServerRequest $request Incoming HTTP request
     * @param string $sessionId MCP session identifier
     * @param (\Closure(string): void)|null $handler Message handler
     * @param string|null $reply Serialized reply payload
     * @param string|null $replySessionId Reply session identifier
     * @param \Closure|null $stream Stream callback
     */
    public function __construct(
        protected ServerRequest $request,
        protected string $sessionId,
        protected ?Closure $handler = null,
        protected ?string $reply = null,
        protected ?string $replySessionId = null,
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
    public function send(string $message, ?string $sessionId = null): void
    {
        if ($this->stream instanceof Closure) {
            $this->sendStreamMessage($message);
        }

        $this->reply = $message;
        $this->replySessionId = $sessionId;
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

        $statusCode = $this->reply === null ? 202 : 200;

        return $this->applyHeaders(new Response([
            'status' => $statusCode,
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
     * Apply MCP session headers to a response.
     *
     * @param \Cake\Http\Response $response Response instance
     * @return \Cake\Http\Response
     */
    protected function applyHeaders(Response $response): Response
    {
        foreach ($this->sessionHeaders() as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }

    /**
     * @inheritDoc
     */
    public function sessionId(): ?string
    {
        return $this->sessionId;
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
     * Build optional MCP session response headers.
     *
     * @return array<string, string>
     */
    protected function sessionHeaders(): array
    {
        if ($this->replySessionId === null) {
            return [];
        }

        return ['MCP-Session-Id' => $this->replySessionId];
    }

    /**
     * @inheritDoc
     */
    public function httpRequest(): ?ServerRequestInterface
    {
        return $this->request;
    }
}
