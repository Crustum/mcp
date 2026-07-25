<?php
declare(strict_types=1);

namespace Crustum\Mcp\Command;

use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Crustum\Mcp\Support\Str;
use Override;

/**
 * Bake command for MCP tool classes.
 *
 * Usage:
 * ```
 * bin/cake bake mcp_tool ExampleTool
 * ```
 */
class McpToolCommand extends McpBakeCommand
{
    /**
     * @inheritDoc
     */
    #[Override]
    public static function defaultName(): string
    {
        return 'bake mcp_tool';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function getDescription(): string
    {
        return 'Bake an MCP tool class';
    }

    /**
     * @inheritDoc
     */
    public function name(): string
    {
        return 'mcp_tool';
    }

    /**
     * @inheritDoc
     */
    protected function pathFragment(): string
    {
        return 'Mcp/Tools/';
    }

    /**
     * @inheritDoc
     */
    protected function namespaceSuffix(): string
    {
        return 'Mcp\\Tools';
    }

    /**
     * @inheritDoc
     */
    protected function templateName(): string
    {
        return 'tool';
    }

    /**
     * @inheritDoc
     */
    protected function typeLabel(): string
    {
        return 'tool';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    protected function templateVariables(string $name, Arguments $args, ConsoleIo $io): array
    {
        return [
            'title' => Str::headline($name),
        ];
    }
}
