<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Reorders Accept headers so application/json is preferred for MCP requests.
 */
class ReorderJsonAccept implements MiddlewareInterface
{
    /**
     * Process the request and reorder Accept headers when needed.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Incoming request
     * @param \Psr\Http\Server\RequestHandlerInterface $handler Request handler
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $accept = $request->getHeaderLine('Accept');

        if ($accept === '' || !str_contains($accept, ',')) {
            return $handler->handle($request);
        }

        $acceptParts = array_map(trim(...), explode(',', $accept));

        usort(
            $acceptParts,
            fn(string $a, string $b): int => str_contains($b, 'application/json') <=> str_contains($a, 'application/json'),
        );

        $request = $request->withHeader('Accept', implode(', ', $acceptParts));

        return $handler->handle($request);
    }
}
