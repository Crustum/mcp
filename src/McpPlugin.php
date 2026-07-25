<?php
declare(strict_types=1);

namespace Crustum\Mcp;

use Cake\Console\CommandCollection;
use Cake\Core\BasePlugin;
use Cake\Core\Configure;
use Cake\Core\ContainerApplicationInterface;
use Cake\Core\ContainerInterface;
use Cake\Core\PluginApplicationInterface;
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
use Crustum\Mcp\Server\Registrar;
use Crustum\Mcp\Support\ContainerRegistry;
use Crustum\Mcp\Support\McpContainerBindings;
use Crustum\Mcp\Support\McpSdk;
use Crustum\Mcp\Support\OAuthDebugLog;
use Crustum\PluginManifest\Manifest\ManifestInterface;
use Crustum\PluginManifest\Manifest\ManifestTrait;
use Crustum\Tessera\ClientRepository;
use Override;
use Throwable;

/**
 * Plugin for Model Context Protocol support.
 *
 * Registers container bindings, MCP OAuth scope, scoped FileLog, routes, commands,
 * and disconnects MCP clients on process shutdown.
 *
 * @uses \Crustum\PluginManifest\Manifest\ManifestTrait
 */
class McpPlugin extends BasePlugin implements ManifestInterface
{
    use ManifestTrait;

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
     * @var bool
     */
    protected bool $middlewareEnabled = false;

    /**
     * Whether the process shutdown disconnect callback was registered.
     *
     * @var bool
     */
    protected static bool $clientDisconnectRegistered = false;

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
     * Bootstrap the plugin.
     *
     * @param \Cake\Core\PluginApplicationInterface $app Application instance
     * @return void
     */
    #[Override]
    public function bootstrap(PluginApplicationInterface $app): void
    {
        parent::bootstrap($app);

        if ($app instanceof ContainerApplicationInterface) {
            ContainerRegistry::setInstance($app->getContainer());
        }

        $this->registerMcpLogger();
        $this->registerMcpScope();
        $this->registerClientDisconnect();
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

    /**
     * Plugin install assets.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function manifest(): array
    {
        $pluginPath = dirname(__DIR__);

        return array_merge(
            static::manifestConfig(
                $pluginPath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'mcp.php',
                CONFIG . 'mcp.php',
                false,
            ),
            static::manifestBootstrapAppend(
                "if (file_exists(CONFIG . 'mcp.php')) {\n    Configure::load('mcp', 'default');\n}",
                '// Mcp Plugin Configuration',
            ),
            static::manifestStarRepo('Crustum/Mcp'),
        );
    }
}
