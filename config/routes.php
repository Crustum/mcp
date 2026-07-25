<?php
declare(strict_types=1);

use Cake\Core\Configure;
use Cake\Routing\RouteBuilder;
use Crustum\Mcp\Server\Registrar;

/**
 * MCP route block.
 *
 * Host applications register MCP servers in config/mcp.php under Mcp.Servers.
 * Optional config/routes/mcp.php remains available for programmatic registration.
 *
 * OAuth well-known + DCR routes are registered outside the MCP middleware stack.
 */
return static function (RouteBuilder $routes): void {
    if (Configure::read('Mcp.oauth.enabled', true)) {
        $prefix = Configure::read('Mcp.oauth.prefix', 'oauth');
        if (!is_string($prefix) || $prefix === '') {
            $prefix = 'oauth';
        }

        Registrar::getInstance()->oauthRoutes($routes, $prefix);
    }

    $middlewareConfig = Configure::read('Mcp.Middleware', []);
    $middlewareNames = [];

    if (is_array($middlewareConfig)) {
        foreach ($middlewareConfig as $alias => $middleware) {
            if (!is_string($alias)) {
                continue;
            }

            if (!is_array($middleware)) {
                continue;
            }

            if (($middleware['apply'] ?? true) === false) {
                continue;
            }

            $middlewareNames[] = $alias;
        }
    }

    $routes->scope('/', static function (RouteBuilder $builder) use ($middlewareNames): void {
        if ($middlewareNames !== []) {
            $builder->applyMiddleware(...$middlewareNames);
        }

        Registrar::getInstance()->ensureConfigured();
        Registrar::getInstance()->connectAllWebRoutes($builder);

        $appMcpRoutes = CONFIG . 'routes' . DS . 'mcp.php';

        if (is_file($appMcpRoutes)) {
            $register = require $appMcpRoutes;

            if (is_callable($register)) {
                $register($builder);
            }
        }
    });
};
