<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Middleware;

use Cake\Http\Response;
use Crustum\Mcp\Enums\ErrorCode;
use Crustum\Mcp\Enums\MetaKey;
use Crustum\Mcp\Enums\RequestHeader;
use Crustum\Mcp\Transport\HeaderValue;
use Crustum\Mcp\Transport\JsonRpcRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Validates the mirrored MCP request headers over HTTP.
 */
class ValidateMcpHeaders implements MiddlewareInterface
{
    /**
     * @inheritDoc
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $body = $this->requestBody($request);

        if (!is_array($body) || !isset($body['id']) || !is_string($body['method'] ?? null)) {
            return $handler->handle($request);
        }

        $message = new JsonRpcRequest(
            id: is_int($body['id']) || is_string($body['id']) ? $body['id'] : 0,
            method: $body['method'],
            params: is_array($body['params'] ?? null) ? $body['params'] : [],
        );

        if ($message->isLegacy()) {
            return $handler->handle($request);
        }

        $mismatch = $this->mismatch($request, RequestHeader::PROTOCOL_VERSION, $message->meta()[MetaKey::PROTOCOL_VERSION->value] ?? null, true)
            ?? $this->mismatch($request, RequestHeader::METHOD, $message->method, true)
            ?? $this->mismatch($request, RequestHeader::NAME, $message->name(), $message->requiresName());

        if ($mismatch === null) {
            return $handler->handle($request);
        }

        $response = (new Response())
            ->withStatus(400)
            ->withHeader('Content-Type', 'application/json');

        $response->getBody()->write((string)json_encode([
            'jsonrpc' => '2.0',
            'id' => $body['id'],
            'error' => [
                'code' => ErrorCode::HEADER_MISMATCH->value,
                'message' => $mismatch,
            ],
        ]));

        return $response;
    }

    /**
     * Decode the JSON-RPC request body.
     *
     * Prefer already-parsed body data: Cake `BodyParserMiddleware` consumes the
     * PSR stream, so `getBody()->getContents()` is often empty on MCP routes.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Server request
     * @return array<string, mixed>|null
     */
    protected function requestBody(ServerRequestInterface $request): ?array
    {
        $parsed = $request->getParsedBody();
        if (is_array($parsed) && $parsed !== []) {
            return $parsed;
        }

        $stream = $request->getBody();
        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        $decoded = json_decode($stream->getContents(), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Compare a mirrored header against its expected value.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Server request
     * @param \Crustum\Mcp\Enums\RequestHeader $header Mirrored header name
     * @param mixed $expected Expected protocol value
     * @param bool $required Whether the header is required
     * @return string|null Mismatch message, or null when valid
     */
    protected function mismatch(ServerRequestInterface $request, RequestHeader $header, mixed $expected, bool $required): ?string
    {
        $value = $request->getHeaderLine($header->value);

        if ($value === '') {
            return $required ? "Header mismatch: The [{$header->value}] header is required." : null;
        }

        $headerValue = $header === RequestHeader::NAME
            ? HeaderValue::fromHeader($value)
            : new HeaderValue($value);

        if (!is_string($expected) || $headerValue->matches($expected)) {
            return null;
        }

        return "Header mismatch: The [{$header->value}] header value [{$headerValue->value}] does not match the request body value [{$expected}].";
    }
}
