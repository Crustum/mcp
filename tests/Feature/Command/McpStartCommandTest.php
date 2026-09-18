<?php
declare(strict_types=1);

use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\Routing\Router;
use Crustum\Mcp\Controller\ServerController;
use Crustum\Mcp\Server\Middleware\AddWwwAuthenticateHeader;
use Crustum\Mcp\Server\Middleware\ValidateMcpHeaders;
use Crustum\Mcp\Server\Registrar;
use Crustum\Mcp\Test\Fixtures\ExampleServer;
use Crustum\Mcp\Test\Fixtures\ServerWithDynamicTools;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Dispatch a JSON-RPC message through the MCP HTTP stack.
 *
 * Cake equivalent of upstream `postJson('test-mcp', ...)`: runs the
 * `ValidateMcpHeaders` middleware with a handler that invokes
 * `ServerController::handle()` for the registered web server.
 *
 * @param array<string, mixed> $message JSON-RPC message
 * @param array<string, string> $headers HTTP headers
 * @param string $serverUri Registered web server URI
 * @return \Cake\Http\Response
 */
function mcpStartHttpPost(array $message, array $headers = [], string $serverUri = 'mcp/test'): Response
{
    $environment = [
        'REQUEST_METHOD' => 'POST',
    ];

    foreach ($headers as $name => $value) {
        $environment['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
    }

    $serverRequest = (new ServerRequest([
        'url' => '/' . $serverUri,
        'environment' => $environment,
        'input' => json_encode($message, JSON_THROW_ON_ERROR),
        'params' => [
            'serverUri' => $serverUri,
        ],
    ]))->withParsedBody($message);

    $handler = new class ($serverRequest) implements RequestHandlerInterface
    {
        public function __construct(private ServerRequestInterface $serverRequest)
        {
        }

        public function handle(ServerRequestInterface $request): ResponseInterface
        {
            $controller = new ServerController($this->serverRequest);
            $controller->setResponse(new Response());

            $response = $controller->handle();

            return $response instanceof Response ? $response : new Response();
        }
    };

    $middleware = new ValidateMcpHeaders();

    $response = $middleware->process($serverRequest, $handler);

    return $response instanceof Response ? $response : new Response();
}

/**
 * Start the shared stdio example server subprocess.
 *
 * A single process serves every stdio test in this file: booting the
 * framework per case is slow, and `StdioTransport::run()` already loops
 * over STDIN lines until EOF.
 *
 * @return void
 */
function mcpStdioStart(): void
{
    if (isset($GLOBALS['mcp_stdio_process']) && is_resource($GLOBALS['mcp_stdio_process'])) {
        return;
    }

    $script = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'Support' . DIRECTORY_SEPARATOR . 'stdio_server.php';

    $process = proc_open(
        [PHP_BINARY, $script],
        [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipes,
    );

    expect($process)->not->toBeFalse('message');
    expect($pipes)->toHaveCount(3, 'message');

    stream_set_timeout($pipes[1], 15);
    stream_set_timeout($pipes[2], 15);

    $GLOBALS['mcp_stdio_process'] = $process;
    $GLOBALS['mcp_stdio_pipes'] = $pipes;
}

/**
 * Send one JSON-RPC message to the shared stdio server and read its reply lines.
 *
 * @param array<string, mixed> $message JSON-RPC message
 * @param int $lines Expected stdout reply lines
 * @return list<array<string, mixed>>
 */
function mcpStdioSend(array $message, int $lines = 1): array
{
    mcpStdioStart();

    $pipes = $GLOBALS['mcp_stdio_pipes'];

    fwrite($pipes[0], json_encode($message, JSON_THROW_ON_ERROR) . PHP_EOL);
    fflush($pipes[0]);

    $output = [];

    for ($i = 0; $i < $lines; $i++) {
        $line = fgets($pipes[1]);

        if (!is_string($line)) {
            $status = proc_get_status($GLOBALS['mcp_stdio_process']);
            $stderr = stream_get_contents($pipes[2]);

            expect($line)->not->toBeFalse(
                'Stdio server produced no reply (running: ' . var_export($status['running'], true) . '). Stderr: ' . $stderr,
            );
        }

        $output[] = $line;
    }

    return parseStdoutJsonRpcMessages(implode('', $output));
}

/**
 * Stop the shared stdio example server subprocess.
 *
 * @return void
 */
function mcpStdioStop(): void
{
    if (!isset($GLOBALS['mcp_stdio_process']) || !is_resource($GLOBALS['mcp_stdio_process'])) {
        return;
    }

    $pipes = $GLOBALS['mcp_stdio_pipes'];

    fclose($pipes[0]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($GLOBALS['mcp_stdio_process']);

    unset($GLOBALS['mcp_stdio_process'], $GLOBALS['mcp_stdio_pipes']);
}

/**
 * Build a 401 challenge handler for WWW-Authenticate tests.
 *
 * @param int $statusCode Response status code
 * @return \Psr\Http\Server\RequestHandlerInterface
 */
function mcpStartChallengeHandler(int $statusCode = 401): RequestHandlerInterface
{
    return new class ($statusCode) implements RequestHandlerInterface
    {
        public function __construct(private int $statusCode)
        {
        }

        public function handle(ServerRequestInterface $request): ResponseInterface
        {
            return new Response(['status' => $this->statusCode, 'body' => '']);
        }
    };
}

/**
 * Build an MCP server request for WWW-Authenticate tests.
 *
 * @param string $path Request path
 * @return \Cake\Http\ServerRequest
 */
function mcpStartChallengeRequest(string $path = '/mcp/test'): ServerRequest
{
    return new ServerRequest([
        'url' => $path,
        'environment' => ['REQUEST_METHOD' => 'POST'],
    ]);
}

beforeAll(function (): void {
    mcpStdioStart();
});

afterAll(function (): void {
    mcpStdioStop();
});

beforeEach(function (): void {
    Registrar::getInstance()->registerWeb('/mcp/test', ExampleServer::class);
    Registrar::getInstance()->registerWeb('/mcp/test-dynamic-tools', ServerWithDynamicTools::class);
});

afterEach(function (): void {
    Registrar::setInstance(null);
    Router::resetRoutes();
});

it('answers the legacy initialize handshake over http', function (): void {
    $response = mcpStartHttpPost(initializeMessage());

    expect($response->getStatusCode())->toBe(200);
    expect(json_decode((string)$response->getBody(), true)['result']['protocolVersion'])->toBe('2025-11-25');
});

it('serves legacy requests without mcp headers over http', function (): void {
    $response = mcpStartHttpPost(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list', 'params' => []]);

    expect($response->getStatusCode())->toBe(200);
    expect(json_decode((string)$response->getBody(), true)['result']['tools'])->not->toBeEmpty();
});

it('does not return a session id over http', function (): void {
    $response = mcpStartHttpPost($message = discoverMessage(), mcpHeaders($message));

    expect($response->getStatusCode())->toBe(200);
    expect($response->hasHeader('MCP-Session-Id'))->toBeFalse();
    expect(json_decode((string)$response->getBody(), true))->toEqual(expectedDiscoverResponse());
});

it('ignores an inbound session id header', function (): void {
    $response = mcpStartHttpPost($message = listToolsMessage(), [
        ...mcpHeaders($message),
        'MCP-Session-Id' => 'stale-session',
    ]);

    expect($response->getStatusCode())->toBe(200);
    expect($response->hasHeader('MCP-Session-Id'))->toBeFalse();
    expect(json_decode((string)$response->getBody(), true))->toEqual(expectedListToolsResponse());
});

it('handles consecutive requests without any prior handshake', function (): void {
    $first = mcpStartHttpPost($message = listToolsMessage(), mcpHeaders($message));
    $second = mcpStartHttpPost($message = callToolMessage(), mcpHeaders($message));

    expect($first->getStatusCode())->toBe(200);
    expect($second->getStatusCode())->toBe(200);
    expect(json_decode((string)$first->getBody(), true))->toEqual(expectedListToolsResponse());
    expect(json_decode((string)$second->getBody(), true))->toEqual(expectedCallToolResponse());
});

it('can list resources over http', function (): void {
    $response = mcpStartHttpPost($message = listResourcesMessage(), mcpHeaders($message));

    expect($response->getStatusCode())->toBe(200);
    expect(json_decode((string)$response->getBody(), true))->toEqual(expectedListResourcesResponse());
});

it('can read a resource over http', function (): void {
    $response = mcpStartHttpPost($message = readResourceMessage(), mcpHeaders($message));

    expect($response->getStatusCode())->toBe(200);
    expect(json_decode((string)$response->getBody(), true))->toEqual(expectedReadResourceResponse());
});

it('can list tools over http', function (): void {
    $response = mcpStartHttpPost($message = listToolsMessage(), mcpHeaders($message));

    expect($response->getStatusCode())->toBe(200);
    expect(json_decode((string)$response->getBody(), true))->toEqual(expectedListToolsResponse());
});

it('can call a tool over http', function (): void {
    $response = mcpStartHttpPost($message = callToolMessage(), mcpHeaders($message));

    expect($response->getStatusCode())->toBe(200);
    expect(json_decode((string)$response->getBody(), true))->toEqual(expectedCallToolResponse());
});

it('can stream a tool response over http', function (): void {
    $response = mcpStartHttpPost(
        $message = callStreamingToolMessage(),
        [...mcpHeaders($message), 'Accept' => 'text/event-stream'],
    );

    expect($response->getStatusCode())->toBe(200);
    expect(strtolower($response->getHeaderLine('Content-Type')))->toContain('text/event-stream');

    // The HTTP transport echoes SSE frames via direct output while the
    // callback body stays empty; capture the flushed frames for parsing.
    ob_start();
    $response->getBody()->getContents();
    $body = (string)ob_get_clean();

    $messages = parseSseJsonRpcMessages($body);

    expect($messages)->toEqual(expectedStreamingToolResponse());
});

it('can discover a server over stdio', function (): void {
    expect(mcpStdioSend(discoverMessage()))->toEqual([expectedDiscoverResponse()]);
});

it('can list tools over stdio', function (): void {
    expect(mcpStdioSend(listToolsMessage()))->toEqual([expectedListToolsResponse()]);
});

it('can call a tool over stdio', function (): void {
    expect(mcpStdioSend(callToolMessage()))->toEqual([expectedCallToolResponse()]);
});

it('can stream a tool response over stdio', function (): void {
    expect(mcpStdioSend(callStreamingToolMessage(), 3))->toEqual(expectedStreamingToolResponse());
});

it('can list dynamically added tools', function (): void {
    $response = mcpStartHttpPost($message = listToolsMessage(), mcpHeaders($message), 'mcp/test-dynamic-tools');

    expect($response->getStatusCode())->toBe(200);
    expect(json_decode((string)$response->getBody(), true))->toEqual(expectedListToolsResponse());
});

it('returns 405 for GET requests to MCP web routes', function (): void {
    $controller = new ServerController(new ServerRequest([
        'url' => '/mcp/test',
        'environment' => ['REQUEST_METHOD' => 'GET'],
    ]));
    $controller->setResponse(new Response());

    $response = $controller->methodNotAllowed();

    expect($response->getStatusCode())->toBe(405);
    expect($response->getHeaderLine('Allow'))->toBe('POST');
});

it('returns 405 for DELETE requests to MCP web routes', function (): void {
    $controller = new ServerController(new ServerRequest([
        'url' => '/mcp/test',
        'environment' => ['REQUEST_METHOD' => 'DELETE'],
    ]));
    $controller->setResponse(new Response());

    $response = $controller->methodNotAllowed();

    expect($response->getStatusCode())->toBe(405);
    expect($response->getHeaderLine('Allow'))->toBe('POST');
});

it('returns OAuth WWW-Authenticate header when OAuth routes are enabled and response is 401', function (): void {
    Registrar::getInstance()->oauthRoutes(mcpRegistrarRouteBuilder());

    $middleware = new AddWwwAuthenticateHeader();
    $response = $middleware->process(mcpStartChallengeRequest('/mcp/test'), mcpStartChallengeHandler(401));

    expect($response->getStatusCode())->toBe(401);

    $wwwAuth = $response->getHeaderLine('WWW-Authenticate');

    expect($wwwAuth)->toContain('Bearer realm="mcp"');
    expect($wwwAuth)->toContain('.well-known/oauth-protected-resource/mcp/test');
});

it('returns OAuth WWW-Authenticate header for nested MCP paths', function (): void {
    Registrar::getInstance()->oauthRoutes(mcpRegistrarRouteBuilder());

    $middleware = new AddWwwAuthenticateHeader();
    $response = $middleware->process(mcpStartChallengeRequest('/mcp/nested/test-oauth-401'), mcpStartChallengeHandler(401));

    expect($response->getStatusCode())->toBe(401);

    $wwwAuth = $response->getHeaderLine('WWW-Authenticate');

    expect($wwwAuth)->toContain('Bearer realm="mcp"');
    expect($wwwAuth)->toContain('.well-known/oauth-protected-resource/mcp/nested/test-oauth-401');
});

it('returns Sanctum WWW-Authenticate header when OAuth routes are not enabled and response is 401', function (): void {
    $middleware = new AddWwwAuthenticateHeader();
    $response = $middleware->process(mcpStartChallengeRequest('/mcp/test'), mcpStartChallengeHandler(401));

    expect($response->getStatusCode())->toBe(401);
    expect($response->getHeaderLine('WWW-Authenticate'))->toBe('Bearer realm="mcp", error="invalid_token"');
});

it('does not add WWW-Authenticate header when response is not 401', function (): void {
    $response = mcpStartHttpPost($message = listToolsMessage(), mcpHeaders($message));

    expect($response->getStatusCode())->toBe(200);
    expect($response->hasHeader('WWW-Authenticate'))->toBeFalse();
});
