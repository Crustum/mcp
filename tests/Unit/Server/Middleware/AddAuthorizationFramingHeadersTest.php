<?php
declare(strict_types=1);

use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Crustum\Mcp\Server\Middleware\AddAuthorizationFramingHeaders;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * @param array<string, string> $headers Response headers
 */
function framingTestHandler(array $headers = []): RequestHandlerInterface
{
    return new class ($headers) implements RequestHandlerInterface {
        /**
         * @param array<string, string> $headers Response headers
         */
        public function __construct(
            protected array $headers,
        ) {
        }

        public function handle(ServerRequestInterface $request): ResponseInterface
        {
            $response = new Response();

            foreach ($this->headers as $name => $value) {
                $response = $response->withHeader($name, $value);
            }

            return $response;
        }
    };
}

function framingTestRequest(string $routeName): ServerRequest
{
    $route = new class ($routeName) {
        public function __construct(
            private string $name,
        ) {
        }

        public function getName(): string
        {
            return $this->name;
        }
    };

    return (new ServerRequest([
        'environment' => ['REQUEST_METHOD' => 'GET'],
        'url' => '/oauth/authorize',
    ]))->withAttribute('route', $route);
}

it('denies framing of the authorization pages', function (string $routeName): void {
    $response = (new AddAuthorizationFramingHeaders())->process(
        framingTestRequest($routeName),
        framingTestHandler(),
    );

    expect($response->getHeaderLine('X-Frame-Options'))->toBe('DENY')
        ->and($response->getHeaderLine('Content-Security-Policy'))->toBe("frame-ancestors 'none'");
})->with([
    'tessera:authorizations.authorize',
    'tessera:authorizations.approve',
    'tessera:authorizations.deny',
    'tessera:device',
    'tessera:device.code',
    'tessera:device.authorizations.authorize',
    'tessera:device.authorizations.approve',
    'tessera:device.authorizations.deny',
]);

it('replaces a weaker frame options header', function (): void {
    $response = (new AddAuthorizationFramingHeaders())->process(
        framingTestRequest('tessera:authorizations.authorize'),
        framingTestHandler(['X-Frame-Options' => 'SAMEORIGIN']),
    );

    expect($response->getHeaderLine('X-Frame-Options'))->toBe('DENY');
});

it('keeps the content security policy the application already set', function (): void {
    $response = (new AddAuthorizationFramingHeaders())->process(
        framingTestRequest('tessera:authorizations.authorize'),
        framingTestHandler(['Content-Security-Policy' => "default-src 'self'"]),
    );

    expect($response->getHeader('Content-Security-Policy'))->toBe(["default-src 'self'", "frame-ancestors 'none'"]);
});

it('leaves other responses untouched', function (): void {
    $route = new class ('mcp') {
        public function __construct(
            private string $name,
        ) {
        }

        public function getName(): string
        {
            return $this->name;
        }
    };

    $request = (new ServerRequest([
        'environment' => ['REQUEST_METHOD' => 'POST'],
        'url' => '/mcp',
    ]))->withAttribute('route', $route);

    $response = (new AddAuthorizationFramingHeaders())->process($request, framingTestHandler());

    expect($response->hasHeader('X-Frame-Options'))->toBeFalse()
        ->and($response->hasHeader('Content-Security-Policy'))->toBeFalse();
});
