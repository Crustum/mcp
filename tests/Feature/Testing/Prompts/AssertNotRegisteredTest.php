<?php
declare(strict_types=1);

use Crustum\Mcp\Test\Fixtures\TellMeHiPrompt;
use Crustum\Mcp\Test\Fixtures\SayHiTool;

it('asserts prompt is not registered', function (): void {
    PromptTestServer::prompts()
        ->assertNotRegistered(SayHiTool::class);
});

it('fails when prompt is registered', function (): void {
    PromptTestServer::prompts()
        ->assertNotRegistered(TellMeHiPrompt::class);
})->throws(\PHPUnit\Framework\AssertionFailedError::class);
