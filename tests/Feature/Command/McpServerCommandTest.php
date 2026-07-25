<?php
declare(strict_types=1);

use Cake\Command\Command;

it('can create a server class', function (): void {
    $this->exec('bake mcp_server TestServer');

    $this->assertExitCode(Command::CODE_SUCCESS);
    expect(mcpBakeClassPath('Servers/TestServer.php'))->toBeFile();
});

it('has a bake template available from the plugin', function (): void {
    expect(mcpBakeTemplatePath('server'))->toBeFile();
});
