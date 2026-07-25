<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Ui;

use Crustum\Mcp\Contracts\Arrayable;

/**
 * MCP UI content security policy configuration.
 *
 * @implements \Crustum\Mcp\Contracts\Arrayable<string, mixed>
 */
final class Csp implements Arrayable
{
    /**
     * Create a new CSP configuration.
     *
     * @param array<int, string>|null $connectDomains Domains allowed for fetch, XHR, or WebSocket
     * @param array<int, string>|null $resourceDomains Domains allowed for images, scripts, styles, and fonts
     * @param array<int, string>|null $frameDomains Domains allowed for nested iframe embedding
     * @param array<int, string>|null $baseUriDomains Allowed URLs for the document base element
     */
    public function __construct(
        protected ?array $connectDomains = null,
        protected ?array $resourceDomains = null,
        protected ?array $frameDomains = null,
        protected ?array $baseUriDomains = null,
    ) {
    }

    /**
     * Create a new CSP builder.
     *
     * @return static
     */
    public static function make(): static
    {
        return new self();
    }

    /**
     * Set connect-src domains.
     *
     * @param array<int, string> $domains Allowed connect domains
     * @return static
     */
    public function connectDomains(array $domains): static
    {
        $this->connectDomains = $domains;

        return $this;
    }

    /**
     * Set default resource domains.
     *
     * @param array<int, string> $domains Allowed resource domains
     * @return static
     */
    public function resourceDomains(array $domains): static
    {
        $this->resourceDomains = $domains;

        return $this;
    }

    /**
     * Set frame-src domains.
     *
     * @param array<int, string> $domains Allowed frame domains
     * @return static
     */
    public function frameDomains(array $domains): static
    {
        $this->frameDomains = $domains;

        return $this;
    }

    /**
     * Set base-uri domains.
     *
     * @param array<int, string> $domains Allowed base URI domains
     * @return static
     */
    public function baseUriDomains(array $domains): static
    {
        $this->baseUriDomains = $domains;

        return $this;
    }

    /**
     * Get CSP configuration as an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'connectDomains' => $this->connectDomains,
            'resourceDomains' => $this->resourceDomains,
            'frameDomains' => $this->frameDomains,
            'baseUriDomains' => $this->baseUriDomains,
        ], static fn(mixed $value): bool => $value !== null && $value !== []);
    }
}
