<?php
declare(strict_types=1);

use Cake\Routing\RouteBuilder;
use Crustum\Mcp\Server\Registrar;

/**
 * Host-application MCP server registration (optional).
 *
 * Preferred registration is config/mcp.php under Mcp.Servers and Mcp.local.
 */
return static function (RouteBuilder $routes): void {
    // Registrar::getInstance()->web($routes, '/mcp/server', \App\Mcp\Servers\MyServer::class);
};
