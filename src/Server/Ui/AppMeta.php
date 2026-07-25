<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Ui;

use Crustum\Mcp\Contracts\Arrayable;
use Crustum\Mcp\Server\Ui\Enums\Library;

/**
 * MCP UI application metadata.
 *
 * @implements \Crustum\Mcp\Contracts\Arrayable<string, mixed>
 */
final class AppMeta implements Arrayable
{
    /**
     * Create a new app metadata configuration.
     *
     * @param \Crustum\Mcp\Server\Ui\Csp|null $csp Content security policy configuration
     * @param \Crustum\Mcp\Server\Ui\Permissions|null $permissions UI permission configuration
     * @param string|null $domain Application domain
     * @param bool|null $prefersBorder Whether the UI prefers a border
     * @param array<int, \Crustum\Mcp\Server\Ui\Enums\Library> $libraries Frontend libraries to include
     */
    public function __construct(
        protected ?Csp $csp = null,
        protected ?Permissions $permissions = null,
        protected ?string $domain = null,
        protected ?bool $prefersBorder = true,
        protected array $libraries = [],
    ) {
    }

    /**
     * Create a new app metadata builder.
     *
     * @return static
     */
    public static function make(): static
    {
        return new self();
    }

    /**
     * Set the content security policy.
     *
     * @param \Crustum\Mcp\Server\Ui\Csp $csp CSP configuration
     * @return static
     */
    public function csp(Csp $csp): static
    {
        $this->csp = $csp;

        return $this;
    }

    /**
     * Set UI permissions.
     *
     * @param \Crustum\Mcp\Server\Ui\Permissions $permissions Permission configuration
     * @return static
     */
    public function permissions(Permissions $permissions): static
    {
        $this->permissions = $permissions;

        return $this;
    }

    /**
     * Set the application domain.
     *
     * @param string $domain Application domain
     * @return static
     */
    public function domain(string $domain): static
    {
        $this->domain = $domain;

        return $this;
    }

    /**
     * Set whether the UI prefers a border.
     *
     * @param bool $prefersBorder Border preference
     * @return static
     */
    public function prefersBorder(bool $prefersBorder = true): static
    {
        $this->prefersBorder = $prefersBorder;

        return $this;
    }

    /**
     * Set frontend libraries to include.
     *
     * @param \Crustum\Mcp\Server\Ui\Enums\Library ...$libraries Libraries to include
     * @return static
     */
    public function libraries(Library ...$libraries): static
    {
        $this->libraries = array_values($libraries);

        return $this;
    }

    /**
     * Get configured frontend libraries.
     *
     * @return array<int, \Crustum\Mcp\Server\Ui\Enums\Library>
     */
    public function getLibraries(): array
    {
        return $this->libraries;
    }

    /**
     * Get app metadata as an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $cspArray = $this->csp?->toArray() ?: [];

        if ($this->libraries !== []) {
            $libraryDomains = [];

            foreach ($this->libraries as $library) {
                foreach ($library->domains() as $domain) {
                    $libraryDomains[] = $domain;
                }
            }

            /** @var array<int, string> $existingDomains */
            $existingDomains = $cspArray['resourceDomains'] ?? [];
            $cspArray['resourceDomains'] = array_values(array_unique(array_merge($existingDomains, $libraryDomains)));
        }

        return array_filter([
            'csp' => $cspArray ?: null,
            'permissions' => $this->permissions?->toArray() ?: null,
            'domain' => $this->domain,
            'prefersBorder' => $this->prefersBorder,
        ], static fn(mixed $value): bool => $value !== null);
    }
}
