<?php
declare(strict_types=1);

use Cake\Command\Command;

it('can create a resource class', function (): void {
    $this->exec('bake mcp_resource TestResource');

    $this->assertExitCode(Command::CODE_SUCCESS);
    expect(mcpBakeClassPath('Resources/TestResource.php'))->toBeFile();
});

it('has a bake template available from the plugin', function (): void {
    expect(mcpBakeTemplatePath('resource'))->toBeFile();
});
