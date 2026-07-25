<?php
declare(strict_types=1);

use Cake\Command\Command;
use Crustum\Mcp\Test\Support\StubRegistrar;

beforeEach(function (): void {
    resetMcpRegistrar();
});

it('starts a registered local server successfully', function (): void {
    $serverCalled = false;
    useMcpRegistrar(new StubRegistrar(
        localServers: [
            'demo' => function () use (&$serverCalled): void {
                $serverCalled = true;
            },
        ],
        servers: [
            'demo' => function () use (&$serverCalled): void {
                $serverCalled = true;
            },
        ],
    ));

    $this->exec('mcp start demo');

    $this->assertExitCode(Command::CODE_SUCCESS);
    expect($serverCalled)->toBeTrue();
});

it('fails when server handle is not found', function (): void {
    useMcpRegistrar(new StubRegistrar());

    $this->exec('mcp start invalid');

    $this->assertExitCode(Command::CODE_ERROR);
    $this->assertErrorContains('MCP Server with name [invalid] not found');
    $this->assertErrorContains('Registrar::local()');
});

it('requires a valid handle argument', function (): void {
    useMcpRegistrar(new StubRegistrar());

    $this->exec('mcp start');

    $this->assertExitCode(Command::CODE_ERROR);
    $this->assertErrorContains('Missing required argument. The `handle` argument is required.');
});

it('accepts a string handle argument', function (): void {
    useMcpRegistrar(new StubRegistrar(
        localServers: [
            'test-handle' => function (): void {
            },
        ],
        servers: [
            'test-handle' => function (): void {
            },
        ],
    ));

    $this->exec('mcp start test-handle');

    $this->assertExitCode(Command::CODE_SUCCESS);
});
