<?php
declare(strict_types=1);

use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Crustum\Mcp\Server\Middleware\ValidateMcpHeaders;
use Crustum\Mcp\Server\Registrar;
use Crustum\Mcp\Test\Fixtures\ExampleServer;
use Crustum\Mcp\Controller\ServerController;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Dispatch a JSON-RPC message through the MCP middleware + server stack.
 *
 * @param array<string, mixed> $message JSON-RPC message
 * @param array<string, string> $headers HTTP headers
 * @return \Cake\Http\Response
 */
function validateHeadersPost(array $message, array $headers = []): Response
{
    $environment = [
        'REQUEST_METHOD' => 'POST',
    ];

    foreach ($headers as $name => $value) {
        $environment['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
    }

    $serverRequest = (new ServerRequest([
        'url' => '/mcp/test',
        'environment' => $environment,
        'input' => json_encode($message, JSON_THROW_ON_ERROR),
        'params' => [
            'serverUri' => 'mcp/test',
        ],
    ]))->withParsedBody($message);

    $handler = new class ($serverRequest) implements RequestHandlerInterface
    {
        public function handle(ServerRequestInterface $request): ResponseInterface
        {
            $controller = new ServerController($request);
            $controller->setResponse(new Response());

            $response = $controller->handle();

            return $response instanceof Response ? $response : new Response();
        }
    };

    $middleware = new ValidateMcpHeaders();

    $response = $middleware->process($serverRequest, $handler);

    return $response instanceof Response ? $response : new Response();
}

beforeEach(function (): void {
    Registrar::getInstance()->registerWeb('/mcp/test', ExampleServer::class);
});

afterEach(function (): void {
    Registrar::setInstance(null);
});

it('accepts a request whose headers mirror the body', function (): void {
    $message = callToolMessage();

    $response = validateHeadersPost($message, mcpHeaders($message));

    expect($response->getStatusCode())->toBe(200);
});

it('rejects a request missing a required header', function (string $header): void {
    $message = callToolMessage();
    $headers = mcpHeaders($message);
    unset($headers[$header]);

    $response = validateHeadersPost($message, $headers);

    expect($response->getStatusCode())->toBe(400);
    expect(json_decode((string)$response->getBody(), true))->toEqual([
        'jsonrpc' => '2.0',
        'id' => $message['id'],
        'error' => [
            'code' => -32020,
            'message' => "Header mismatch: The [{$header}] header is required.",
        ],
    ]);
})->with(['MCP-Protocol-Version', 'Mcp-Method', 'Mcp-Name']);

it('rejects a request whose header contradicts the body', function (): void {
    $message = callToolMessage();

    $response = validateHeadersPost($message, [
        ...mcpHeaders($message),
        'Mcp-Name' => 'some-other-tool',
    ]);

    expect($response->getStatusCode())->toBe(400);
    expect(json_decode((string)$response->getBody(), true)['error'])->toEqual([
        'code' => -32020,
        'message' => "Header mismatch: The [Mcp-Name] header value [some-other-tool] does not match the request body value [{$message['params']['name']}].",
    ]);
});

it('rejects a protocol version header that contradicts the body meta', function (): void {
    $message = listToolsMessage();

    $response = validateHeadersPost($message, [
        ...mcpHeaders($message),
        'MCP-Protocol-Version' => '2025-11-25',
    ]);

    expect($response->getStatusCode())->toBe(400);
    expect(json_decode((string)$response->getBody(), true)['error']['code'])->toBe(-32020);
});

it('decodes a base64 sentinel name header before comparing it', function (): void {
    $message = callToolMessage();

    $response = validateHeadersPost($message, [
        ...mcpHeaders($message),
        'Mcp-Name' => '=?base64?' . base64_encode((string)$message['params']['name']) . '?=',
    ]);

    expect($response->getStatusCode())->toBe(200);
});

it('does not require a name header for methods without a named target', function (): void {
    $message = listToolsMessage();

    $response = validateHeadersPost($message, mcpHeaders($message));

    expect($response->getStatusCode())->toBe(200);
    expect(mcpHeaders($message))->not->toHaveKey('Mcp-Name');
});

it('answers an unsupported protocol version with a 400', function (): void {
    $message = listToolsMessage();
    $message['params']['_meta']['io.modelcontextprotocol/protocolVersion'] = '2025-11-25';

    $response = validateHeadersPost($message, [
        ...mcpHeaders($message),
        'MCP-Protocol-Version' => '2025-11-25',
    ]);

    expect($response->getStatusCode())->toBe(400);
    expect(json_decode((string)$response->getBody(), true)['error'])->toEqual([
        'code' => -32022,
        'message' => 'Unsupported protocol version',
        'data' => [
            'supported' => ['2026-07-28'],
            'requested' => '2025-11-25',
        ],
    ]);
});

it('answers missing protocol metadata with a 400', function (): void {
    $message = listToolsMessage();
    unset($message['params']['_meta']['io.modelcontextprotocol/clientCapabilities']);

    $response = validateHeadersPost($message, mcpHeaders($message));

    expect($response->getStatusCode())->toBe(400);
    expect(json_decode((string)$response->getBody(), true)['error']['code'])->toBe(-32602);
});

it('answers an unknown method with a 404', function (): void {
    $message = [
        'jsonrpc' => '2.0',
        'id' => 9,
        'method' => 'unknown/method',
        'params' => ['_meta' => protocolMeta()],
    ];

    $response = validateHeadersPost($message, mcpHeaders($message));

    expect($response->getStatusCode())->toBe(404);
    expect(json_decode((string)$response->getBody(), true)['error']['code'])->toBe(-32601);
});

it('requires the protocol version header even when the body omits the meta member', function (): void {
    $message = listToolsMessage();
    unset($message['params']['_meta']['io.modelcontextprotocol/protocolVersion']);

    $headers = mcpHeaders($message);
    unset($headers['MCP-Protocol-Version']);

    $response = validateHeadersPost($message, $headers);

    expect($response->getStatusCode())->toBe(400);
    expect(json_decode((string)$response->getBody(), true)['error'])->toEqual([
        'code' => -32020,
        'message' => 'Header mismatch: The [MCP-Protocol-Version] header is required.',
    ]);
});

it('skips header validation for a legacy request without protocol metadata', function (): void {
    $message = ['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/list', 'params' => []];

    $response = validateHeadersPost($message);

    expect($response->getStatusCode())->toBe(200);
    expect(json_decode((string)$response->getBody(), true)['result']['tools'])->not->toBeEmpty();
});

it('requires the name header even when the body carries no usable name', function (): void {
    $message = callToolMessage();
    $message['params']['name'] = 123;

    $headers = mcpHeaders($message);
    unset($headers['Mcp-Name']);

    $response = validateHeadersPost($message, $headers);

    expect($response->getStatusCode())->toBe(400);
    expect(json_decode((string)$response->getBody(), true)['error'])->toEqual([
        'code' => -32020,
        'message' => 'Header mismatch: The [Mcp-Name] header is required.',
    ]);
});

it('does not decode the base64 sentinel on headers other than the name', function (): void {
    $message = callToolMessage();

    $response = validateHeadersPost($message, [
        ...mcpHeaders($message),
        'Mcp-Method' => '=?base64?' . base64_encode('tools/call') . '?=',
    ]);

    expect($response->getStatusCode())->toBe(400);
    expect(json_decode((string)$response->getBody(), true)['error']['code'])->toBe(-32020);
});

it('answers an internal error with a 500', function (): void {
    config(['debug' => false]);

    $message = listToolsMessage();

    $response = validateHeadersPost($message, mcpHeaders($message));

    expect($response->getStatusCode())->toBe(200);
});
