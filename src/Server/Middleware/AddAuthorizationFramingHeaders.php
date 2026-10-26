<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Deny framing of the OAuth authorization pages.
 */
class AddAuthorizationFramingHeaders implements MiddlewareInterface
{
    /**
     * Authorization route names receiving frame protection.
     *
     * @var array<int, string>
     */
    protected array $authorizationRoutes = [
        'tessera:authorizations.authorize',
        'tessera:authorizations.approve',
        'tessera:authorizations.deny',
        'tessera:device',
        'tessera:device.code',
        'tessera:device.authorizations.authorize',
        'tessera:device.authorizations.approve',
        'tessera:device.authorizations.deny',
    ];

    /**
     * @inheritDoc
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        $route = $request->getAttribute('route');

        $name = is_object($route) && method_exists($route, 'getName') ? (string)$route->getName() : '';

        if (!in_array($name, $this->authorizationRoutes, true)) {
            return $response;
        }

        $response = $response->withHeader('X-Frame-Options', 'DENY');

        if ($response->getHeaderLine('Content-Security-Policy') === '') {
            return $response->withHeader('Content-Security-Policy', "frame-ancestors 'none'");
        }

        return $response->withAddedHeader('Content-Security-Policy', "frame-ancestors 'none'");
    }
}
