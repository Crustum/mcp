<?php
declare(strict_types=1);

use Crustum\Mcp\Manifest\McpManifest;
use Crustum\Mcp\McpPlugin;
use Crustum\PluginManifest\Manifest\ManifestResolver;

it('emits config, bootstrap and star operations', function (): void {
    $tags = array_column(McpManifest::manifest(), 'tag');

    expect($tags)->toContain('config')
        ->and($tags)->toContain('bootstrap')
        ->and($tags)->toContain('star-repo');
});

it('is discoverable through the resolver', function (): void {
    expect(ManifestResolver::providerClass(McpPlugin::class))->toBe(McpManifest::class)
        ->and(ManifestResolver::manifestFor(McpPlugin::class))->toBe(McpManifest::manifest());
});

it('leaves the plugin class without a manifest contract', function (): void {
    expect(method_exists(McpPlugin::class, 'manifest'))->toBeFalse();
});
