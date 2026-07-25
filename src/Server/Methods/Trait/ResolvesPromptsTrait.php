<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Methods\Trait;

use Crustum\Mcp\Server\Prompt;
use Crustum\Mcp\Server\ServerContext;
use InvalidArgumentException;

/**
 * Resolves MCP prompts from server context.
 */
trait ResolvesPromptsTrait
{
    /**
     * Resolve a prompt by name from the server context.
     *
     * @param string|null $name Prompt name
     * @param \Crustum\Mcp\Server\ServerContext $context Server context
     * @return \Crustum\Mcp\Server\Prompt
     * @throws \InvalidArgumentException
     */
    protected function resolvePrompt(?string $name, ServerContext $context): Prompt
    {
        if (!$name) {
            throw new InvalidArgumentException('Missing [name] parameter.');
        }

        $prompt = $context->prompts()->filter(
            fn(Prompt $prompt): bool => $prompt->name() === $name,
        )->first();

        if ($prompt === null) {
            throw new InvalidArgumentException("Prompt [{$name}] not found.");
        }

        return $prompt;
    }
}
