<?php
declare(strict_types=1);

namespace Crustum\Mcp;

use Cake\Console\CommandCollection;
use Cake\Core\BasePlugin;
use Cake\Core\Configure;
use Cake\Core\ContainerInterface;
use Cake\Core\PluginApplicationInterface;
use Cake\Http\MiddlewareQueue;
use Cake\Log\Engine\FileLog;
use Cake\Log\Log;
use Cake\Routing\RouteBuilder;
use Crustum\Mcp\Client\ClientManager;
use Crustum\Mcp\Command\McpAppResourceCommand;
use Crustum\Mcp\Command\McpInspectorCommand;
use Crustum\Mcp\Command\McpPromptCommand;
use Crustum\Mcp\Command\McpResourceCommand;
use Crustum\Mcp\Command\McpServerCommand;
use Crustum\Mcp\Command\McpStartCommand;
use Crustum\Mcp\Command\McpToolCommand;
use Crustum\Mcp\Server\ContainerInvoker;
use Crustum\Mcp\Server\Middleware\AddAuthorizationFramingHeaders;
use Crustum\Mcp\Server\Registrar;
use Crustum\Mcp\Support\ContainerRegistry;
use Crustum\Mcp\Support\McpContainerBindings;
use Crustum\Mcp\Support\McpSdk;
use Crustum\Mcp\Support\OAuthDebugLog;
use Crustum\Tessera\ClientRepository;
use Override;
use Throwable;

/**
 * Plugin for Model Context Protocol support.
 *
 * Registers container bindings, MCP OAuth scope, scoped FileLog, routes, commands,
 * and disconnects MCP clients on process shutdown.
 */
class McpPlugin extends BasePlugin
{
    /**
     * Plugin name.
     *
     * @var string|null
     */
    protected ?string $name = 'Mcp';

    /**
     * Do bootstrapping or not.
     *
     * @var bool
     */
    protected bool $bootstrapEnabled = true;

    /**
     * Load routes or not.
     *
     * @var bool
     */
    protected bool $routesEnabled = true;

    /**
     * Console middleware enabled.
     *
     * @var bool
     */
    protected bool $consoleEnabled = true;

    /**
     * HTTP middleware enabled.
     *
     * Registers the authorization framing middleware globally so the OAuth
     * authorize and device pages cannot be framed.
     *
     * @var bool
     */
    protected bool $middlewareEnabled = true;

    /**
     * Whether the process shutdown disconnect callback was registered.
     *
     * @var bool
     */
    protected static bool $clientDisconnectRegistered = false;

    /**
     * Bootstrap the plugin.
     *
     * @param \Cake\Core\PluginApplicationInterface $app Application instance
     * @return void
     */
    #[Override]
    public function bootstrap(PluginApplicationInterface $app): void
    {
        parent::bootstrap($app);

        if (!Configure::check('Mcp')) {
            if (file_exists(CONFIG . 'mcp.php')) {
                Configure::load('mcp', 'default');
            } elseif (file_exists($this->getConfigPath() . 'mcp.php')) {
                Configure::load('Crustum/Mcp.mcp', 'default', false);
            }
        }

        $app->getEventManager()->on('Application.buildContainer', function ($event): void {
            $container = $event->getData('container');
            ContainerRegistry::setInstance($container);
        });

        $this->registerMcpLogger();
        $this->registerMcpScope();
        $this->registerClientDisconnect();
    }

    /**
     * Register global HTTP middleware.
     *
     * Adds frame protection headers to the OAuth authorize and device pages.
     *
     * @param \Cake\Http\MiddlewareQueue $middlewareQueue Middleware queue
     * @return \Cake\Http\MiddlewareQueue
     */
    #[Override]
    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        $middlewareQueue->add(new AddAuthorizationFramingHeaders());

        return $middlewareQueue;
    }

    /**
     * Register plugin services in the container.
     *
     * @param \Cake\Core\ContainerInterface $container Container instance
     * @return void
     */
    public function services(ContainerInterface $container): void
    {
        $container->addShared(ClientManager::class, fn(): ClientManager => ClientManager::getInstance());
        $container->addShared(Registrar::class, fn(): Registrar => Registrar::getInstance());
        $container->addShared(ClientRepository::class, fn(): ClientRepository => new ClientRepository());
        $container->addShared(
            McpContainerBindings::SDK,
            static fn(): string => McpSdk::contents(),
        );
        $container->addShared(ContainerInvoker::class, fn(): ContainerInvoker => new ContainerInvoker($container));
    }

    /**
     * Register route middleware and load plugin routes.
     *
     * @param \Cake\Routing\RouteBuilder $routes Route builder instance
     * @return void
     */
    #[Override]
    public function routes(RouteBuilder $routes): void
    {
        $middlewares = Configure::read('Mcp.Middleware', []);

        if (is_array($middlewares)) {
            foreach ($middlewares as $alias => $middleware) {
                if (!is_string($alias)) {
                    continue;
                }

                if (!is_array($middleware)) {
                    continue;
                }

                $class = $middleware['class'] ?? null;

                if (!is_string($class)) {
                    continue;
                }

                if (array_key_exists('request', $middleware)) {
                    $requestClass = $middleware['request'];

                    if (!is_string($requestClass)) {
                        continue;
                    }

                    $request = new $requestClass();

                    if (array_key_exists('method', $middleware) && is_string($middleware['method'])) {
                        $request = $request->{$middleware['method']}();
                    }

                    if (array_key_exists('params', $middleware) && is_array($middleware['params'])) {
                        $routes->registerMiddleware($alias, new $class($request, $middleware['params']));
                    } else {
                        $routes->registerMiddleware($alias, new $class($request));
                    }
                } elseif (array_key_exists('params', $middleware) && is_array($middleware['params'])) {
                    $routes->registerMiddleware($alias, new $class($middleware['params']));
                } else {
                    $routes->registerMiddleware($alias, new $class());
                }
            }
        }

        parent::routes($routes);
    }

    /**
     * Add commands for the plugin.
     *
     * @param \Cake\Console\CommandCollection $commands The command collection to update
     * @return \Cake\Console\CommandCollection
     */
    #[Override]
    public function console(CommandCollection $commands): CommandCollection
    {
        $commands = parent::console($commands);
        $commands->add('bake mcp_server', McpServerCommand::class);
        $commands->add('bake mcp_tool', McpToolCommand::class);
        $commands->add('bake mcp_prompt', McpPromptCommand::class);
        $commands->add('bake mcp_resource', McpResourceCommand::class);
        $commands->add('bake mcp_app_resource', McpAppResourceCommand::class);
        $commands->add('mcp start', McpStartCommand::class);
        $commands->add('mcp inspector', McpInspectorCommand::class);

        return $commands;
    }

    /**
     * Get plugin name.
     *
     * @return string
     */
    #[Override]
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Register a FileLog engine for the `mcp` scope when the host has not.
     *
     * Skips when a logger named `mcp` already exists (for example host `Log.mcp`
     * in `config/app.php`). Does nothing when `LOGS` is undefined.
     *
     * @return void
     */
    protected function registerMcpLogger(): void
    {
        $name = OAuthDebugLog::SCOPE;

        if (in_array($name, Log::configured(), true)) {
            return;
        }

        if (!defined('LOGS')) {
            return;
        }

        Log::setConfig($name, [
            'className' => FileLog::class,
            'path' => LOGS,
            'file' => $name,
            'scopes' => [$name],
            'levels' => ['notice', 'info', 'debug', 'warning', 'error', 'critical', 'alert', 'emergency'],
        ]);
    }

    /**
     * Register the MCP OAuth scope with Tessera when available.
     *
     * @return void
     */
    protected function registerMcpScope(): void
    {
        Registrar::ensureMcpScope();
    }

    /**
     * Disconnect MCP clients when the PHP process shuts down.
     *
     * @return void
     */
    protected function registerClientDisconnect(): void
    {
        if (static::$clientDisconnectRegistered) {
            return;
        }

        static::$clientDisconnectRegistered = true;

        register_shutdown_function(static function (): void {
            try {
                ClientManager::getInstance()->disconnectAll();
            } catch (Throwable) {
            }
        });
    }
}
