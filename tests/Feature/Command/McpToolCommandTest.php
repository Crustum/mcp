<?php
declare(strict_types=1);

use Cake\Command\Command;
use Cake\Routing\Router;
use TestApp\Application;

it('can create a tool class', function (): void {
    $this->setAppNamespace('TestApp');
    $this->configApplication(Application::class, [CONFIG]);
    Router::reload();
    $this->loadPlugins([
        // 'Bake',
    ]);

    $this->exec('bake mcp_tool TestTool');

    $this->assertExitCode(Command::CODE_SUCCESS);
    expect(mcpBakeClassPath('Tools/TestTool.php'))->toBeFile();
});

it('has a bake template available from the plugin', function (): void {
    expect(mcpBakeTemplatePath('tool'))->toBeFile();
});
