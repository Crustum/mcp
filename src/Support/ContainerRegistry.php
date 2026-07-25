<?php
declare(strict_types=1);

namespace Crustum\Mcp\Support;

use Cake\Core\Container;
use Cake\Core\ContainerInterface;

/**
 * Global container registry for MCP.
 *
 * Provides static access to CakePHP container for use in production
 * (set by plugin) and tests (set manually).
 */
class ContainerRegistry
{
    /**
     * Shared container instance.
     *
     * @var \Cake\Core\ContainerInterface|null
     */
    protected static ?ContainerInterface $instance = null;

    /**
     * Get the global container instance.
     *
     * @return \Cake\Core\ContainerInterface
     */
    public static function getInstance(): ContainerInterface
    {
        return static::$instance ??= new Container();
    }

    /**
     * Set the global container instance.
     *
     * @param \Cake\Core\ContainerInterface|null $instance Container instance
     * @return void
     */
    public static function setInstance(?ContainerInterface $instance): void
    {
        static::$instance = $instance;
    }
}
