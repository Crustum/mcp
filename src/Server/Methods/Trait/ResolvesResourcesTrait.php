<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Methods\Trait;

use Crustum\Mcp\Server\Contracts\HasUriTemplate;
use Crustum\Mcp\Server\Resource;
use Crustum\Mcp\Server\ServerContext;
use InvalidArgumentException;

/**
 * Resolves MCP resources from server context.
 */
trait ResolvesResourcesTrait
{
    /**
     * Resolve a resource by URI from the server context.
     *
     * @param string|null $uri Resource URI
     * @param \Crustum\Mcp\Server\ServerContext $context Server context
     * @return \Crustum\Mcp\Server\Resource
     * @throws \InvalidArgumentException
     */
    protected function resolveResource(?string $uri, ServerContext $context): Resource
    {
        if (!$uri) {
            throw new InvalidArgumentException('Missing [uri] parameter.');
        }

        $resource = $context->resources()->filter(
            fn(Resource $resource): bool => $resource->uri() === $uri,
        )->first() ?? $context->resourceTemplates()->filter(
            fn(Resource $template): bool => $template instanceof HasUriTemplate
                && ((string)$template->uriTemplate() === $uri
                    || $template->uriTemplate()->match($uri) !== null),
        )->first();

        if (!$resource) {
            throw new InvalidArgumentException("Resource [{$uri}] not found.");
        }

        return $resource;
    }
}
