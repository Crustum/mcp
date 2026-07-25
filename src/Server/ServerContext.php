<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server;

use Cake\Collection\Collection;
use Crustum\Mcp\Schema\Implementation;
use Crustum\Mcp\Server\Contracts\HasUriTemplate;
use Crustum\Mcp\Support\ContainerRegistry;

/**
 * MCP server runtime context for JSON-RPC method handlers.
 */
class ServerContext
{
    /**
     * @param array<int, string> $supportedProtocolVersions Supported MCP protocol versions
     * @param array<string, mixed> $serverCapabilities Registered server capabilities
     * @param \Crustum\Mcp\Schema\Implementation $implementation Server implementation metadata
     * @param string $instructions Server instructions
     * @param int $maxPaginationLength Maximum pagination page size
     * @param int $defaultPaginationLength Default pagination page size
     * @param array<int, \Crustum\Mcp\Server\Tool|string> $tools Registered tools
     * @param array<int, \Crustum\Mcp\Server\Resource|string> $resources Registered resources
     * @param array<int, \Crustum\Mcp\Server\Prompt|string> $prompts Registered prompts
     */
    public function __construct(
        public array $supportedProtocolVersions,
        public array $serverCapabilities,
        public Implementation $implementation,
        public string $instructions,
        public int $maxPaginationLength,
        public int $defaultPaginationLength,
        protected array $tools,
        protected array $resources,
        protected array $prompts,
    ) {
    }

    /**
     * Get registered tools eligible for listing.
     *
     * @return \Cake\Collection\Collection<int, \Crustum\Mcp\Server\Tool>
     */
    public function tools(): Collection
    {
        return $this->resolveTools(new Collection($this->tools));
    }

    /**
     * Get registered static resources eligible for listing.
     *
     * @return \Cake\Collection\Collection<int, \Crustum\Mcp\Server\Resource>
     */
    public function resources(): Collection
    {
        $resources = new Collection($this->resources);

        return $this->resolveResources(
            $resources->filter(fn(Resource|string $resource): bool => !$this->isResourceTemplate($resource)),
        );
    }

    /**
     * Get registered resource templates eligible for listing.
     *
     * @return \Cake\Collection\Collection<int, \Crustum\Mcp\Server\Contracts\HasUriTemplate&\Crustum\Mcp\Server\Resource>
     */
    public function resourceTemplates(): Collection
    {
        $resources = new Collection($this->resources);

        return $this->resolveResourceTemplates(
            $resources->filter(fn(Resource|string $resource): bool => $this->isResourceTemplate($resource)),
        );
    }

    /**
     * Get registered prompts eligible for listing.
     *
     * @return \Cake\Collection\Collection<int, \Crustum\Mcp\Server\Prompt>
     */
    public function prompts(): Collection
    {
        return $this->resolvePrompts(new Collection($this->prompts));
    }

    /**
     * Resolve the effective page size for a list request.
     *
     * @param int|null $requestedPerPage Requested page size
     * @return int
     */
    public function perPage(?int $requestedPerPage = null): int
    {
        return min($requestedPerPage ?? $this->defaultPaginationLength, $this->maxPaginationLength);
    }

    /**
     * Determine whether the server advertises a capability.
     *
     * @param string $capability Capability name
     * @return bool
     */
    public function hasCapability(string $capability): bool
    {
        return array_key_exists($capability, $this->serverCapabilities);
    }

    /**
     * Resolve tool class names to instances and filter registration eligibility.
     *
     * @param \Cake\Collection\Collection<int, \Crustum\Mcp\Server\Tool|string> $tools Tool collection
     * @return \Cake\Collection\Collection<int, \Crustum\Mcp\Server\Tool>
     */
    private function resolveTools(Collection $tools): Collection
    {
        return $tools
            ->map(function (Tool|string $tool): Tool {
                if (!is_string($tool)) {
                    return $tool;
                }

                $container = ContainerRegistry::getInstance();

                return $container->has($tool) ? $container->get($tool) : new $tool();
            })
            ->filter(fn(Tool $tool): bool => $tool->eligibleForRegistration());
    }

    /**
     * Resolve resource class names to instances and filter registration eligibility.
     *
     * @param \Cake\Collection\Collection<int, \Crustum\Mcp\Server\Resource|string> $resources Resource collection
     * @return \Cake\Collection\Collection<int, \Crustum\Mcp\Server\Resource>
     */
    private function resolveResources(Collection $resources): Collection
    {
        return $resources
            ->map(fn(Resource|string $resource): Resource => is_string($resource) ? new $resource() : $resource)
            ->filter(fn(Resource $resource): bool => $resource->eligibleForRegistration());
    }

    /**
     * Resolve resource template class names to instances and filter registration eligibility.
     *
     * @param \Cake\Collection\Collection<int, \Crustum\Mcp\Server\Resource|string> $resources Resource collection
     * @return \Cake\Collection\Collection<int, \Crustum\Mcp\Server\Contracts\HasUriTemplate&\Crustum\Mcp\Server\Resource>
     */
    private function resolveResourceTemplates(Collection $resources): Collection
    {
        return $resources
            ->map(function (Resource|string $resource): Resource {
                if (is_string($resource)) {
                    return new $resource();
                }

                return $resource;
            })
            ->filter(fn(Resource $resource): bool => $resource instanceof HasUriTemplate && $resource->eligibleForRegistration());
    }

    /**
     * Resolve prompt class names to instances and filter registration eligibility.
     *
     * @param \Cake\Collection\Collection<int, \Crustum\Mcp\Server\Prompt|string> $prompts Prompt collection
     * @return \Cake\Collection\Collection<int, \Crustum\Mcp\Server\Prompt>
     */
    private function resolvePrompts(Collection $prompts): Collection
    {
        return $prompts
            ->map(fn(Prompt|string $prompt): Prompt => is_string($prompt) ? new $prompt() : $prompt)
            ->filter(fn(Prompt $prompt): bool => $prompt->eligibleForRegistration());
    }

    /**
     * Determine whether a resource entry represents a URI template.
     *
     * @param \Crustum\Mcp\Server\Resource|string $resource Resource instance or class name
     * @return bool
     */
    private function isResourceTemplate(Resource|string $resource): bool
    {
        return $resource instanceof HasUriTemplate
            || (is_string($resource) && is_subclass_of($resource, HasUriTemplate::class));
    }
}
