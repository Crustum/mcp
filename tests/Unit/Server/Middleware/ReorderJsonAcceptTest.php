<?php
declare(strict_types=1);

use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Crustum\Mcp\Server\Middleware\ReorderJsonAccept;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * @param array<string, string> $environment
 * @return \Cake\Http\ServerRequest
 */
function mcpAcceptRequest(array $environment = []): ServerRequest
{
    return new ServerRequest(['environment' => $environment]);
}

/**
 * @param callable(\Psr\Http\Message\ServerRequestInterface): void|null $assertion
 * @return \Psr\Http\Server\RequestHandlerInterface
 */
function mcpAcceptHandler(?callable $assertion = null): RequestHandlerInterface
{
    return new class ($assertion) implements RequestHandlerInterface
    {
        /**
         * @param (callable(\Psr\Http\Message\ServerRequestInterface): void)|null $assertion
         */
        public function __construct(private $assertion)
        {
        }

        /**
         * @inheritDoc
         */
        public function handle(ServerRequestInterface $request): ResponseInterface
        {
            if ($this->assertion !== null) {
                ($this->assertion)($request);
            }

            return new Response([
                'body' => 'middleware worked',
                'type' => 'text/plain',
                'charset' => 'UTF-8',
            ]);
        }
    };
}

it('leaves single accept header unchanged', function (): void {
    $request = mcpAcceptRequest(['HTTP_ACCEPT' => 'application/json']);
    $middleware = new ReorderJsonAccept();

    $middleware->process($request, mcpAcceptHandler(
        fn(ServerRequestInterface $processed): mixed => expect($processed->getHeaderLine('Accept'))->toBe('application/json'),
    ));
});

it('leaves non-comma separated accept header unchanged', function (): void {
    $request = mcpAcceptRequest(['HTTP_ACCEPT' => 'text/html']);
    $middleware = new ReorderJsonAccept();

    $middleware->process($request, mcpAcceptHandler(
        fn(ServerRequestInterface $processed): mixed => expect($processed->getHeaderLine('Accept'))->toBe('text/html'),
    ));
});

it('reorders multiple accept headers to prioritize json', function (): void {
    $request = mcpAcceptRequest(['HTTP_ACCEPT' => 'text/html, application/json, text/plain']);
    $middleware = new ReorderJsonAccept();

    $middleware->process($request, mcpAcceptHandler(
        fn(ServerRequestInterface $processed): mixed => expect($processed->getHeaderLine('Accept'))->toBe('application/json, text/html, text/plain'),
    ));
});

it('handles json already first in list', function (): void {
    $request = mcpAcceptRequest(['HTTP_ACCEPT' => 'application/json, text/html, text/plain']);
    $middleware = new ReorderJsonAccept();

    $middleware->process($request, mcpAcceptHandler(
        fn(ServerRequestInterface $processed): mixed => expect($processed->getHeaderLine('Accept'))->toBe('application/json, text/html, text/plain'),
    ));
});

it('handles multiple json types correctly', function (): void {
    $request = mcpAcceptRequest(['HTTP_ACCEPT' => 'text/html, application/json, application/vnd.api+json, text/plain']);
    $middleware = new ReorderJsonAccept();

    $middleware->process($request, mcpAcceptHandler(function (ServerRequestInterface $processed): void {
        $parts = array_map(trim(...), explode(',', $processed->getHeaderLine('Accept')));

        expect($parts)->toMatchArray(['application/json', 'text/html', 'application/vnd.api+json', 'text/plain'])
            ->and(count($parts))->toBe(4);
    }));
});

it('handles accept header with quality values', function (): void {
    $request = mcpAcceptRequest(['HTTP_ACCEPT' => 'text/html;q=0.9, application/json;q=0.8, text/plain;q=0.7']);
    $middleware = new ReorderJsonAccept();

    $middleware->process($request, mcpAcceptHandler(function (ServerRequestInterface $processed): void {
        $parts = array_map(trim(...), explode(',', $processed->getHeaderLine('Accept')));

        expect($parts[0])->toBe('application/json;q=0.8');
    }));
});

it('handles whitespace in accept header', function (): void {
    $request = mcpAcceptRequest(['HTTP_ACCEPT' => '  text/html  ,  application/json  ,  text/plain  ']);
    $middleware = new ReorderJsonAccept();

    $middleware->process($request, mcpAcceptHandler(
        fn(ServerRequestInterface $processed): mixed => expect($processed->getHeaderLine('Accept'))->toBe('application/json, text/html, text/plain'),
    ));
});

it('handles no json in accept header', function (): void {
    $request = mcpAcceptRequest(['HTTP_ACCEPT' => 'text/html, text/plain, image/png']);
    $middleware = new ReorderJsonAccept();

    $middleware->process($request, mcpAcceptHandler(
        fn(ServerRequestInterface $processed): mixed => expect($processed->getHeaderLine('Accept'))->toBe('text/html, text/plain, image/png'),
    ));
});

it('handles empty accept header', function (): void {
    $request = mcpAcceptRequest(['HTTP_ACCEPT' => '']);
    $middleware = new ReorderJsonAccept();

    $middleware->process($request, mcpAcceptHandler(
        fn(ServerRequestInterface $processed): mixed => expect($processed->getHeaderLine('Accept'))->toBe(''),
    ));
});

it('handles missing accept header', function (): void {
    $request = mcpAcceptRequest();
    $middleware = new ReorderJsonAccept();

    $response = $middleware->process($request, mcpAcceptHandler());

    expect((string)$response->getBody())->toBe('middleware worked');
});

it('passes request through middleware correctly', function (): void {
    $request = mcpAcceptRequest(['HTTP_ACCEPT' => 'text/html, application/json']);
    $middleware = new ReorderJsonAccept();

    $response = $middleware->process($request, mcpAcceptHandler(
        fn(ServerRequestInterface $processed): mixed => expect($processed->getHeaderLine('Accept'))->toBe('application/json, text/html'),
    ));

    expect((string)$response->getBody())->toBe('middleware worked');
});
