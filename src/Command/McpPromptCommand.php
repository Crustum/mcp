<?php
declare(strict_types=1);

namespace Crustum\Mcp\Command;

use Override;

/**
 * Bake command for MCP prompt classes.
 *
 * Usage:
 * ```
 * bin/cake bake mcp_prompt ExamplePrompt
 * ```
 */
class McpPromptCommand extends McpBakeCommand
{
    /**
     * @inheritDoc
     */
    #[Override]
    public static function defaultName(): string
    {
        return 'bake mcp_prompt';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function getDescription(): string
    {
        return 'Bake an MCP prompt class';
    }

    /**
     * @inheritDoc
     */
    public function name(): string
    {
        return 'mcp_prompt';
    }

    /**
     * @inheritDoc
     */
    protected function pathFragment(): string
    {
        return 'Mcp/Prompts/';
    }

    /**
     * @inheritDoc
     */
    protected function namespaceSuffix(): string
    {
        return 'Mcp\\Prompts';
    }

    /**
     * @inheritDoc
     */
    protected function templateName(): string
    {
        return 'prompt';
    }

    /**
     * @inheritDoc
     */
    protected function typeLabel(): string
    {
        return 'prompt';
    }
}
