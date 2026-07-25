<?php
declare(strict_types=1);

namespace Crustum\Mcp\Command;

use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Override;

/**
 * Bake command for MCP server classes.
 *
 * Usage:
 * ```
 * bin/cake bake mcp_server ExampleServer
 * ```
 */
class McpServerCommand extends McpBakeCommand
{
    /**
     * @inheritDoc
     */
    #[Override]
    public static function defaultName(): string
    {
        return 'bake mcp_server';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function getDescription(): string
    {
        return 'Bake an MCP server class';
    }

    /**
     * @inheritDoc
     */
    public function name(): string
    {
        return 'mcp_server';
    }

    /**
     * @inheritDoc
     */
    protected function pathFragment(): string
    {
        return 'Mcp/Servers/';
    }

    /**
     * @inheritDoc
     */
    protected function namespaceSuffix(): string
    {
        return 'Mcp\\Servers';
    }

    /**
     * @inheritDoc
     */
    protected function templateName(): string
    {
        return 'server';
    }

    /**
     * @inheritDoc
     */
    protected function typeLabel(): string
    {
        return 'server';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    protected function templateVariables(string $name, Arguments $args, ConsoleIo $io): array
    {
        $serverDisplayName = trim((string)preg_replace('/(?<!^)([A-Z])/', ' $1', $name));

        return [
            'serverDisplayName' => $serverDisplayName,
        ];
    }
}
