<?php
declare(strict_types=1);

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\Exception\ConsoleException;
use Cake\Console\TestSuite\StubConsoleOutput;
use Cake\Core\Configure;
use Crustum\Mcp\Command\McpInspectorCommand;
use Crustum\Mcp\Server\Registrar;
use Crustum\Mcp\Server\ServerUrl;
use Crustum\Mcp\Server\WebServerRegistration;
use Crustum\Mcp\Test\Fixtures\ExampleServer;
use Crustum\Mcp\Test\Support\StubRegistrar;

beforeEach(function (): void {
    resetMcpRegistrar();
    ServerUrl::resolveUsing(null);
    Configure::delete('Mcp.base_url');
    Configure::delete('App.fullBaseUrl');
});

it('normalizes windows paths for external process arguments', function (): void {
    $command = new McpInspectorCommand();
    $normalized = invokeMcpCommandMethod(
        $command,
        'externalProcessPath',
        ['D:\\projects\\_demo_db\\testqa\\bin\\cake.php'],
    );

    expect($normalized)->toBe('D:/projects/_demo_db/testqa/bin/cake.php');
});

it('normalizes mixed filesystem paths correctly', function (): void {
    $command = new McpInspectorCommand();

    $cases = [
        'D:\\Herd\\cyborgfinance\\bin\\cake.php' => 'D:/Herd/cyborgfinance/bin/cake.php',
        '/var/www/cakephp/bin/cake.php' => '/var/www/cakephp/bin/cake.php',
        'C:\\xampp\\htdocs\\project\\bin\\cake.php' => 'C:/xampp/htdocs/project/bin/cake.php',
    ];

    foreach ($cases as $input => $expected) {
        expect(invokeMcpCommandMethod($command, 'externalProcessPath', [$input]))->toBe($expected);
    }
});

it('fails with an invalid handle', function (): void {
    useMcpRegistrar(new StubRegistrar(
        servers: [
            'demo' => function (): void {
            },
            'weather' => function (): void {
            },
        ],
    ));

    $this->exec('mcp inspector invalid');

    $this->assertExitCode(Command::CODE_ERROR);
    $this->assertOutputContains('Starting the MCP Inspector for server [invalid]');
    $this->assertErrorContains('MCP Server with name [invalid] not found');
});

it('requires a valid handle argument', function (): void {
    useMcpRegistrar(new StubRegistrar());

    $this->exec('mcp inspector');

    $this->assertExitCode(Command::CODE_ERROR);
    $this->assertErrorContains('Missing required argument. The `handle` argument is required.');
});

it('fails when no servers are registered', function (): void {
    useMcpRegistrar(new StubRegistrar(servers: []));

    $this->exec('mcp inspector demo');

    $this->assertExitCode(Command::CODE_ERROR);
    $this->assertOutputContains('Starting the MCP Inspector for server [demo]');
    $this->assertErrorContains('No MCP servers found');
    $this->assertErrorContains('bake mcp_server');
});

it('rejects non-string handle arguments', function (): void {
    useMcpRegistrar(new StubRegistrar(
        servers: ['demo' => function (): void {
        }],
    ));

    expect(function (): void {
        $args = new Arguments([123], [], ['handle']);
        $io = new ConsoleIo(new StubConsoleOutput(), new StubConsoleOutput());
        (new McpInspectorCommand())->execute($args, $io);
    })->toThrow(ConsoleException::class);
});

it('uses the only registered server when a single server exists', function (): void {
    $callable = function (): void {
    };

    useMcpRegistrar(new StubRegistrar(servers: ['demo' => $callable]));

    expect($callable)->toBeCallable();
});

it('fails when the only registered server has an unknown type', function (): void {
    useMcpRegistrar(new StubRegistrar(servers: ['single' => new stdClass()]));

    $this->exec('mcp inspector demo');

    $this->assertExitCode(Command::CODE_ERROR);
    $this->assertErrorContains('MCP Server with name [demo] not found');
});

it('fails when a web server URL is not absolute', function (): void {
    $registration = new WebServerRegistration('mcp/qa', ExampleServer::class);

    useMcpRegistrar(new StubRegistrar(
        webServers: ['mcp/qa' => $registration],
        servers: ['mcp/qa' => $registration],
    ));

    $this->exec('mcp inspector mcp/qa');

    $this->assertExitCode(Command::CODE_ERROR);
    $this->assertErrorContains('absolute server URL');
});

it('resolves an absolute web server URL from the url option', function (): void {
    $command = new McpInspectorCommand();
    $args = new Arguments(['demo'], ['url' => 'http://qa.zz/mcp/qa'], ['handle']);

    $url = invokeMcpCommandMethod($command, 'resolveWebServerUrl', [$args, 'mcp/qa']);

    expect($url)->toBe('http://qa.zz/mcp/qa');
});

it('resolves web server registrations by uri', function (): void {
    $registration = new WebServerRegistration('api/mcp', ExampleServer::class);

    useMcpRegistrar(new StubRegistrar(
        webServers: ['api/mcp' => $registration],
    ));

    expect(Registrar::getInstance()->getWebServer('api/mcp'))->toBe($registration);
    expect($registration->uri)->toBe('api/mcp');
});

it('retrieves a php binary path', function (): void {
    $phpBinary = invokeMcpCommandMethod(new McpInspectorCommand(), 'phpBinary');

    expect($phpBinary)->toBeString();
    expect(strlen((string)$phpBinary))->toBeGreaterThan(0);
});
