<?php
declare(strict_types=1);

use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\Routing\Router;
use Crustum\Mcp\Server\Middleware\AddWwwAuthenticateHeader;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * @return \Psr\Http\Server\RequestHandlerInterface
 */
function challengeHandler(int $statusCode = 401): RequestHandlerInterface
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

function mcpRequest(string $path = '/mcp/demo'): ServerRequest
{
    return new ServerRequest([
        'url' => $path,
        'environment' => ['REQUEST_METHOD' => 'POST'],
    ]);
}

beforeEach(function (): void {
    Router::resetRoutes();
});

it('adds WWW-Authenticate header on MCP route with 401', function (): void {
    $middleware = new AddWwwAuthenticateHeader();
    $response = $middleware->process(mcpRequest(), challengeHandler());

    expect($response->getHeaderLine('WWW-Authenticate'))
        ->toContain('Bearer realm="mcp"');
});

it('does not add header on non-MCP route with 401', function (): void {
    $middleware = new AddWwwAuthenticateHeader();
    $response = $middleware->process(mcpRequest('/api/users'), challengeHandler());

    expect($response->hasHeader('WWW-Authenticate'))->toBeFalse();
});

it('does not add header when status is not 401', function (): void {
    $middleware = new AddWwwAuthenticateHeader();
    $response = $middleware->process(mcpRequest(), challengeHandler(200));

    expect($response->hasHeader('WWW-Authenticate'))->toBeFalse();
});
