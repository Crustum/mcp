<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Conformance\Prompts;

use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Prompt;
use Crustum\Mcp\Server\Conformance\Fixtures;

class PromptWithImage extends Prompt
{
    protected string $name = 'test_prompt_with_image';

    protected string $description = 'A prompt that includes image content';

    /**
     * @return array<int, \Crustum\Mcp\Response>
     */
    public function handle(): array
    {
        return [
            Response::image(Fixtures::IMAGE_BASE64, 'image/png'),
            Response::text('Please analyze the image above.'),
        ];
    }
}
