<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server;

use Crustum\Mcp\Server\Attributes\AppMeta as AppMetaAttribute;
use Crustum\Mcp\Server\Ui\AppMeta;
use Crustum\Mcp\Server\Ui\Enums\Library;
use Crustum\Mcp\Support\Str;
use Override;

/**
 * Base MCP app resource primitive for UI-backed resources.
 */
abstract class AppResource extends Resource
{
    /**
     * Claude MCP content domain suffix.
     */
    public const CLAUDE_DOMAIN_SUFFIX = '.claudemcpcontent.com';

    /**
     * @var string
     */
    protected string $mimeType = 'text/html;profile=mcp-app';

    /**
     * @var string
     */
    protected string $defaultUriScheme = 'ui';

    /**
     * Get the UI metadata for this app resource.
     *
     * @return \Crustum\Mcp\Server\Ui\AppMeta
     */
    public function appMeta(): AppMeta
    {
        $attribute = $this->resolveAttribute(AppMetaAttribute::class);

        return $attribute?->toAppMeta() ?? new AppMeta();
    }

    /**
     * Get resolved UI metadata including the Claude domain when absent.
     *
     * @return array<string, mixed>
     */
    public function resolvedAppMeta(): array
    {
        $appMeta = $this->appMeta()->toArray();

        if (!isset($appMeta['domain'])) {
            $appMeta['domain'] = $this->toClaudeDomain(ServerUrl::current());
        }

        return $appMeta;
    }

    /**
     * Build HTML script tags for configured UI libraries.
     *
     * @return string
     */
    public function libraryScripts(): string
    {
        return implode("\n", array_map(
            fn(Library $library): string => implode("\n", $library->scriptTags()),
            $this->appMeta()->getLibraries(),
        ));
    }

    /**
     * Get the resource's array representation.
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray(): array
    {
        $data = parent::toArray();
        $appMeta = $this->resolvedAppMeta();

        if ($appMeta !== []) {
            $data['_meta'] = array_merge($data['_meta'] ?? [], ['ui' => $appMeta]);
        }

        return $data;
    }

    /**
     * Compute a Claude MCP content domain from the server route.
     *
     * @param string $serverRoute Current server route URL
     * @return string
     */
    private function toClaudeDomain(string $serverRoute): string
    {
        return Str::of(hash('sha256', $serverRoute))
            ->limit(32, '')
            ->append(self::CLAUDE_DOMAIN_SUFFIX)
            ->value();
    }
}
