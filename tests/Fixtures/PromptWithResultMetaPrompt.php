<?php
declare(strict_types=1);

namespace Crustum\Mcp\Test\Fixtures;

use Crustum\Mcp\Response;
use Crustum\Mcp\ResponseFactory;
use Crustum\Mcp\Server\Prompt;

class PromptWithResultMetaPrompt extends Prompt
{
    protected string $description = 'Prompt with result-level meta';

    public function handle(): ResponseFactory
    {
        return Response::make(
            Response::text('Prompt instructions with result meta')->withMeta(['key' => 'value']),
        )->withMeta([
            'prompt_version' => '2.0',
            'last_updated' => '2025-01-01',
        ]);
    }
}
