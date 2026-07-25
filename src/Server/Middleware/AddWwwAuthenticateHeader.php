<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Middleware;

use Cake\Routing\Router;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Adds WWW-Authenticate headers to unauthorized MCP responses.
 */
class AddWwwAuthenticateHeader implements MiddlewareInterface
{
    /**
     * Process the request and enrich 401 responses with authentication metadata.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Incoming request
     * @param \Psr\Http\Server\RequestHandlerInterface $handler Request handler
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        if ($response->getStatusCode() !== 401) {
            return $response;
        }

        $namedRoutes = Router::getRouteCollection()->named();
        $isOauth = array_key_exists('mcp.oauth.protected-resource.nested', $namedRoutes);

        if ($isOauth) {
            $resourceMetadata = Router::url([
                '_name' => 'mcp.oauth.protected-resource.nested',
                'path' => ltrim($request->getUri()->getPath(), '/'),
            ], true);

            return $response->withHeader(
                'WWW-Authenticate',
                'Bearer realm="mcp", resource_metadata="' . $resourceMetadata . '"',
            );
        }

        return $response->withHeader(
            'WWW-Authenticate',
            'Bearer realm="mcp", error="invalid_token"',
        );
    }
}
