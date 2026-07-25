<?php
declare(strict_types=1);

namespace Crustum\Mcp\Test\Fixtures;

use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Prompt;

class TellMeHiPrompt extends Prompt
{
    protected string $description = 'Instructions for how too tell me hi';

    public function handle(): Response
    {
        return Response::text('Here are the instructions on how to tell me hi');
    }
}
