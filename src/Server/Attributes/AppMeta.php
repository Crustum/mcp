<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Attributes;

use Attribute;
use Crustum\Mcp\Server\Ui\AppMeta as AppMetaData;
use Crustum\Mcp\Server\Ui\Csp;
use Crustum\Mcp\Server\Ui\Permissions;

/**
 * MCP app metadata attribute for resources.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class AppMeta
{
    /**
     * @param array<int, string>|null $connectDomains Domains allowed for fetch, XHR, or WebSocket
     * @param array<int, string>|null $resourceDomains Domains allowed for static resources
     * @param array<int, string>|null $frameDomains Domains allowed for nested iframes
     * @param array<int, string>|null $baseUriDomains Allowed base URI domains
     * @param array<int, \Crustum\Mcp\Server\Ui\Enums\Permission>|null $permissions Allowed UI permissions
     * @param bool|null $prefersBorder Whether the UI prefers a border
     * @param string|null $domain Application domain
     * @param array<int, \Crustum\Mcp\Server\Ui\Enums\Library> $libraries Frontend libraries to include
     */
    public function __construct(
        public readonly ?array $connectDomains = null,
        public readonly ?array $resourceDomains = null,
        public readonly ?array $frameDomains = null,
        public readonly ?array $baseUriDomains = null,
        public readonly ?array $permissions = null,
        public readonly ?bool $prefersBorder = null,
        public readonly ?string $domain = null,
        public readonly array $libraries = [],
    ) {
    }

    /**
     * Convert the attribute to UI app metadata.
     *
     * @return \Crustum\Mcp\Server\Ui\AppMeta
     */
    public function toAppMeta(): AppMetaData
    {
        $meta = AppMetaData::make();

        $csp = $this->getCsp();

        if ($csp instanceof Csp) {
            $meta->csp($csp);
        }

        if ($this->permissions !== null) {
            $meta->permissions(Permissions::make()->allow(...$this->permissions));
        }

        if ($this->prefersBorder !== null) {
            $meta->prefersBorder($this->prefersBorder);
        }

        if ($this->domain !== null) {
            $meta->domain($this->domain);
        }

        if ($this->libraries !== []) {
            $meta->libraries(...$this->libraries);
        }

        return $meta;
    }

    /**
     * Build CSP configuration from attribute values.
     *
     * @return \Crustum\Mcp\Server\Ui\Csp|null
     */
    protected function getCsp(): ?Csp
    {
        if (
            $this->connectDomains === null
            && $this->resourceDomains === null
            && $this->frameDomains === null
            && $this->baseUriDomains === null
        ) {
            return null;
        }

        return new Csp(
            connectDomains: $this->connectDomains,
            resourceDomains: $this->resourceDomains,
            frameDomains: $this->frameDomains,
            baseUriDomains: $this->baseUriDomains,
        );
    }
}
