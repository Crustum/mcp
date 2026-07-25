<?php
declare(strict_types=1);

use Cake\Command\Command;

it('can create a prompt class', function (): void {
    $this->exec('bake mcp_prompt TestPrompt');

    $this->assertExitCode(Command::CODE_SUCCESS);
    expect(mcpBakeClassPath('Prompts/TestPrompt.php'))->toBeFile();
});

it('has a bake template available from the plugin', function (): void {
    expect(mcpBakeTemplatePath('prompt'))->toBeFile();
});
