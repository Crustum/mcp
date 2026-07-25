<?php
declare(strict_types=1);

use Cake\Core\Container;
use Cake\Log\Engine\FileLog;
use Cake\Log\Log;
use Crustum\Mcp\Client\ClientManager;
use Crustum\Mcp\McpPlugin;
use Crustum\Mcp\Server\ContainerInvoker;
use Crustum\Mcp\Server\Registrar;
use Crustum\Mcp\Support\ContainerRegistry;
use Crustum\Mcp\Support\McpContainerBindings;
use Crustum\Mcp\Support\McpSdk;
use Crustum\Mcp\Support\OAuthDebugLog;
use Crustum\Tessera\ClientRepository;
use Crustum\Tessera\Tessera;
use TestApp\Application;

beforeEach(function (): void {
    Tessera::$scopes = [];
    ClientManager::setInstance(null);
    if (in_array(OAuthDebugLog::SCOPE, Log::configured(), true)) {
        Log::drop(OAuthDebugLog::SCOPE);
    }
});

afterEach(function (): void {
    if (in_array(OAuthDebugLog::SCOPE, Log::configured(), true)) {
        Log::drop(OAuthDebugLog::SCOPE);
    }
});

it('registers shared container services', function (): void {
    $container = new Container();
    $plugin = new McpPlugin();

    $plugin->services($container);

    expect($container->has(ClientManager::class))->toBeTrue();
    expect($container->has(Registrar::class))->toBeTrue();
    expect($container->has(ClientRepository::class))->toBeTrue();
    expect($container->has(ContainerInvoker::class))->toBeTrue();
    expect($container->has(McpContainerBindings::SDK))->toBeTrue();
    expect($container->get(McpContainerBindings::SDK))->toBe(McpSdk::contents());
});

it('registers the mcp oauth scope during bootstrap', function (): void {
    Tessera::$scopes = [];

    $plugin = new McpPlugin([
        'path' => dirname(__DIR__, 2) . DIRECTORY_SEPARATOR,
    ]);
    $plugin->bootstrap(new Application(CONFIG));

    expect(Tessera::$scopes)->toHaveKey(Registrar::OAUTH_SCOPE);
    expect(Tessera::$scopes[Registrar::OAUTH_SCOPE])->toBe('Use MCP server');
});

it('registers an mcp FileLog when the host has not configured one', function (): void {
    expect(Log::configured())->not->toContain(OAuthDebugLog::SCOPE);

    $plugin = new McpPlugin([
        'path' => dirname(__DIR__, 2) . DIRECTORY_SEPARATOR,
    ]);
    $plugin->bootstrap(new Application(CONFIG));

    expect(Log::configured())->toContain(OAuthDebugLog::SCOPE);
    expect(Log::engine(OAuthDebugLog::SCOPE))->toBeInstanceOf(FileLog::class);
});

it('leaves an existing mcp logger configuration untouched', function (): void {
    Log::setConfig(OAuthDebugLog::SCOPE, [
        'className' => FileLog::class,
        'path' => LOGS,
        'file' => 'mcp-host',
        'scopes' => [OAuthDebugLog::SCOPE],
        'levels' => ['error'],
    ]);
    $existing = Log::engine(OAuthDebugLog::SCOPE);

    $plugin = new McpPlugin([
        'path' => dirname(__DIR__, 2) . DIRECTORY_SEPARATOR,
    ]);
    $plugin->bootstrap(new Application(CONFIG));

    expect(Log::engine(OAuthDebugLog::SCOPE))->toBe($existing);
});

it('sets the container registry from the application container', function (): void {
    $app = new Application(CONFIG);
    $plugin = new McpPlugin([
        'path' => dirname(__DIR__, 2) . DIRECTORY_SEPARATOR,
    ]);

    $plugin->bootstrap($app);

    expect(ContainerRegistry::getInstance())->toBe($app->getContainer());
});

it('loads the mcp sdk from resources js', function (): void {
    $contents = McpSdk::contents();

    expect($contents)->not->toBe('');
    expect($contents)->toContain('function');
});
