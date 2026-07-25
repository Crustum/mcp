<?php
declare(strict_types=1);

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Crustum\Mcp\Client\ClientManager;
use Crustum\Mcp\Client\OAuth\OAuthRouteRegistrar;
use Crustum\Mcp\Test\Support\HttpFake;
use Crustum\Mcp\Test\TestCase\McpTestCase;

pest()->extend(McpTestCase::class)->in('TestCase', 'Feature', 'Unit');

uses(ConsoleIntegrationTestTrait::class)->in('Feature/Command', 'Unit/Command');

pest()->beforeEach(function (): void {
    ClientManager::setInstance(new ClientManager());
})->in('Unit/Client');

pest()->afterEach(function (): void {
    HttpFake::clear();
    ClientManager::setInstance(null);
    resetMcpSession();
})->in('Unit/Client');

pest()->afterEach(function (): void {
    resetMcpRequestBuilder();
})->in('Feature');

expect()->extend('toBeOne', fn() => $this->toBe(1));

require __DIR__ . '/Support/client_helpers.php';
require __DIR__ . '/Support/server_helpers.php';
require __DIR__ . '/Support/command_helpers.php';
require __DIR__ . '/Support/bake_helpers.php';
require __DIR__ . '/Support/registrar_helpers.php';
require __DIR__ . '/Support/feature_helpers.php';
require __DIR__ . '/Support/validation_helpers.php';

pest()->beforeEach(function (): void {
    $this->configApplication(\TestApp\Application::class, [CONFIG]);
    cleanMcpBakeArtifacts();
})->in('Feature/Command');

pest()->beforeEach(function (): void {
    $this->configApplication(\TestApp\Application::class, [CONFIG]);
    resetMcpRegistrar();
})->in('Unit/Command');

pest()->afterEach(function (): void {
    resetMcpRegistrar();
})->in('Unit/Command');

pest()->afterEach(function (): void {
    cleanMcpBakeArtifacts();
})->in('Feature/Command');

pest()->beforeEach(function (): void {
    ClientManager::setInstance(new ClientManager());
    OAuthRouteRegistrar::clearHandlers();
    resetMcpRegistrarState();
})->in('Feature/Client');

pest()->afterEach(function (): void {
    HttpFake::clear();
    ClientManager::setInstance(null);
    OAuthRouteRegistrar::clearHandlers();
    resetMcpSession();
    resetMcpRegistrarState();
})->in('Feature/Client');

class_alias(\Crustum\Mcp\Test\Support\Http::class, 'Http');
class_alias(\Crustum\Mcp\Test\Support\Session::class, 'Session');
