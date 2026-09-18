<?php
declare(strict_types=1);

use Crustum\Mcp\Server;
use Crustum\Mcp\Test\Fixtures\TellMeHiPrompt;
use Crustum\Mcp\Test\Fixtures\SayHiTool;

class PromptTestServer extends Server
{
    protected array $prompts = [
        TellMeHiPrompt::class,
    ];
}

it('asserts registered prompts', function (): void {
    PromptTestServer::prompts()
        ->assertRegistered([TellMeHiPrompt::class]);
});

it('asserts registered prompts by class name string', function (): void {
    PromptTestServer::prompts()
        ->assertRegistered(TellMeHiPrompt::class);
});

it('fails when prompt is not registered', function (): void {
    PromptTestServer::prompts()
        ->assertRegistered(SayHiTool::class);
})->throws(\PHPUnit\Framework\AssertionFailedError::class);
