<?php
declare(strict_types=1);

namespace Crustum\Mcp\Command;

use Override;

/**
 * Bake command for MCP resource classes.
 *
 * Usage:
 * ```
 * bin/cake bake mcp_resource ExampleResource
 * ```
 */
class McpResourceCommand extends McpBakeCommand
{
    /**
     * @inheritDoc
     */
    #[Override]
    public static function defaultName(): string
    {
        return 'bake mcp_resource';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function getDescription(): string
    {
        return 'Bake an MCP resource class';
    }

    /**
     * @inheritDoc
     */
    public function name(): string
    {
        return 'mcp_resource';
    }

    /**
     * @inheritDoc
     */
    protected function pathFragment(): string
    {
        return 'Mcp/Resources/';
    }

    /**
     * @inheritDoc
     */
    protected function namespaceSuffix(): string
    {
        return 'Mcp\\Resources';
    }

    /**
     * @inheritDoc
     */
    protected function templateName(): string
    {
        return 'resource';
    }

    /**
     * @inheritDoc
     */
    protected function typeLabel(): string
    {
        return 'resource';
    }
}
