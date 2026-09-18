<?php
declare(strict_types=1);

use Boundwize\StructArmed\Architecture;

return Architecture::define()
    ->layerPattern('Plugin', '/^Crustum\\\\Mcp\\\\McpPlugin$/')
    ->layerPattern('Controller', '/^Crustum\\\\Mcp\\\\Controller\\\\.*$/')
    ->layerPattern('Command', '/^Crustum\\\\Mcp\\\\Command\\\\.*$/')
    ->layerPattern('Server', '/^Crustum\\\\Mcp\\\\(Server|Response|ResponseFactory)(\\\\.*)?$/')
    ->layerPattern('Client', [
        '/^Crustum\\\\Mcp\\\\Client(\\\\.*)?$/',
        '/^Crustum\\\\Mcp\\\\WebClient$/',
    ])
    ->layerPattern('Foundation', [
        '/^Crustum\\\\Mcp\\\\Request$/',
        '/^Crustum\\\\Mcp\\\\(Contracts|Enums|Event|Exception|Schema|Support|Trait|Transport)\\\\.*$/',
    ])
    ->layerPattern('Tessera', '/^Crustum\\\\Tessera\\\\.*$/')
    ->layerPattern('PluginManifest', '/^Crustum\\\\PluginManifest\\\\.*$/')
    ->layerPattern('JsonSchema', '/^Crustum\\\\JsonSchema\\\\.*$/')
    ->ruleset([
        'Plugin' => ['Command', 'Server', 'Client', 'Foundation', 'Tessera', 'PluginManifest'],
        'Controller' => ['Server', 'Client', 'Foundation', 'Tessera'],
        'Command' => ['Server', 'Foundation'],
        'Server' => ['Client', 'Foundation', 'Tessera', 'JsonSchema'],
        'Client' => ['Foundation'],
        'Foundation' => [],
    ])
    ->skipClassViolation('Crustum\\Mcp\\Event\\SessionInitializedEvent', 'Crustum\\Mcp\\Server');
