<?php
declare(strict_types=1);

use Cake\Core\Configure;
use Cake\Routing\Router;
use Crustum\Mcp\Server\ServerUrl;

beforeEach(function (): void {
    ServerUrl::resolveUsing(null);
    Configure::delete('Mcp.base_url');
    Configure::delete('App.fullBaseUrl');
    Router::reload();
});

afterEach(function (): void {
    ServerUrl::resolveUsing(null);
    Configure::delete('Mcp.base_url');
    Configure::delete('App.fullBaseUrl');
    Router::reload();
});

it('builds an absolute MCP server URL from the configured base URL', function (): void {
    Configure::write('App.fullBaseUrl', 'http://example.com');

    expect(ServerUrl::forPath('mcp/qa'))->toBe('http://example.com/mcp/qa');
    expect(ServerUrl::isAbsolute('http://example.com/mcp/qa'))->toBeTrue();
});

it('returns a relative path when no base URL is configured', function (): void {
    expect(ServerUrl::forPath('mcp/qa'))->toBe('/mcp/qa');
    expect(ServerUrl::isAbsolute('/mcp/qa'))->toBeFalse();
});

it('prefers Mcp.base_url over App.fullBaseUrl', function (): void {
    Configure::write('Mcp.base_url', 'http://mcp.example.com');
    Configure::write('App.fullBaseUrl', 'http://example.com');

    expect(ServerUrl::current())->toBe('http://mcp.example.com');
});
