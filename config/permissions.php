<?php
declare(strict_types=1);

/**
 * CakeDC Auth public-route fragment for MCP OAuth discovery, DCR, and HTTP servers.
 *
 * Merge into host `config/permissions.php`:
 *
 * ```php
 * use Cake\Core\Plugin;
 *
 * $permissions = array_merge(
 *     $permissions,
 *     require Plugin::path('Crustum/Mcp') . 'config' . DS . 'permissions.php',
 * );
 * ```
 *
 * The `Server` rule is needed once MCP runs through `ServerController` on a CakeDC
 * host (so anonymous JSON-RPC is not redirected to login). Hosts without CakeDC Auth
 * can omit merging this fragment.
 *
 * @return list<array<string, mixed>>
 */
return [
    [
        'prefix' => false,
        'plugin' => 'Crustum/Mcp',
        'controller' => 'OAuthMetadata',
        'action' => '*',
        'bypassAuth' => true,
    ],
    [
        'prefix' => false,
        'plugin' => 'Crustum/Mcp',
        'controller' => 'OAuthRegister',
        'action' => ['register'],
        'bypassAuth' => true,
    ],
    [
        'prefix' => false,
        'plugin' => 'Crustum/Mcp',
        'controller' => 'Server',
        'action' => '*',
        'bypassAuth' => true,
    ],
];
