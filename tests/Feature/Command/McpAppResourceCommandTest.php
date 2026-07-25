<?php
declare(strict_types=1);

use Cake\Command\Command;

it('can create an app resource class', function (): void {
    $this->exec('bake mcp_app_resource TestAppResource');

    $this->assertExitCode(Command::CODE_SUCCESS);
    expect(mcpBakeClassPath('Resources/TestAppResource.php'))->toBeFile();
});

it('creates a view alongside the php class', function (): void {
    $this->exec('bake mcp_app_resource DashboardResource');

    $this->assertExitCode(Command::CODE_SUCCESS);
    expect(mcpBakeClassPath('Resources/DashboardResource.php'))->toBeFile()
        ->and(mcpBakeViewPath('dashboard-resource.php'))->toBeFile();
});

it('does not generate a js entry file', function (): void {
    $this->exec('bake mcp_app_resource DashboardResource');

    $this->assertExitCode(Command::CODE_SUCCESS);
    expect(APP . 'webroot' . DS . 'js' . DS . 'mcp' . DS . 'dashboard-resource.js')->not->toBeFile();
});

it('generates php class that extends app resource', function (): void {
    $this->exec('bake mcp_app_resource DashboardResource');

    $this->assertExitCode(Command::CODE_SUCCESS);
    $content = (string)file_get_contents(mcpBakeClassPath('Resources/DashboardResource.php'));

    expect($content)->toContain('extends AppResource');
});

it('generates view with createMcpApp inline script', function (): void {
    $this->exec('bake mcp_app_resource DashboardResource');

    $this->assertExitCode(Command::CODE_SUCCESS);
    $content = (string)file_get_contents(mcpBakeViewPath('dashboard-resource.php'));

    expect($content)->toContain('createMcpApp')
        ->and($content)->not->toContain('entry=');
});

it('has bake templates available from the plugin', function (): void {
    expect(mcpBakeTemplatePath('app_resource'))->toBeFile()
        ->and(mcpBakeTemplatePath('app_resource_view'))->toBeFile();
});

it('preserves namespace segments in view paths', function (): void {
    $this->exec('bake mcp_app_resource Admin/DashboardApp');

    $this->assertExitCode(Command::CODE_SUCCESS);
    expect(mcpBakeClassPath('Resources/Admin/DashboardApp.php'))->toBeFile()
        ->and(mcpBakeViewPath('admin/dashboard-app.php'))->toBeFile();

    $content = (string)file_get_contents(mcpBakeClassPath('Resources/Admin/DashboardApp.php'));

    expect($content)->toContain("Response::view('Mcp/admin/dashboard-app'");
});

it('generates unique views for different namespaces with same class name', function (): void {
    $this->exec('bake mcp_app_resource Admin/DashboardApp');
    $this->assertExitCode(Command::CODE_SUCCESS);

    $this->exec('bake mcp_app_resource Reports/DashboardApp');
    $this->assertExitCode(Command::CODE_SUCCESS);

    expect(mcpBakeViewPath('admin/dashboard-app.php'))->toBeFile()
        ->and(mcpBakeViewPath('reports/dashboard-app.php'))->toBeFile();
});

it('generates stub without unused permission imports', function (): void {
    $this->exec('bake mcp_app_resource CleanResource');

    $this->assertExitCode(Command::CODE_SUCCESS);
    $content = (string)file_get_contents(mcpBakeClassPath('Resources/CleanResource.php'));

    expect($content)->not->toContain('Permission');
});

it('respects force flag for existing files', function (): void {
    $this->exec('bake mcp_app_resource DashboardResource');
    $this->assertExitCode(Command::CODE_SUCCESS);

    $this->exec('bake mcp_app_resource DashboardResource --force');
    $this->assertExitCode(Command::CODE_SUCCESS);

    expect(mcpBakeViewPath('dashboard-resource.php'))->toBeFile();
});
