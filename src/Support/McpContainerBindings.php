<?php
declare(strict_types=1);

namespace Crustum\Mcp\Support;

use Cake\Core\Container;
use Cake\Core\ContainerInterface;
use Crustum\Mcp\Request;
use League\Container\Definition\DefinitionAggregate;
use ReflectionProperty;

/**
 * Per-RPC scoped bindings for the Cake/League container.
 *
 * League Container requires the third argument on add() to overwrite an
 * existing definition when rebinding per-request values.
 */
final class McpContainerBindings
{
    public const REQUEST = 'mcp.request';

    public const SDK = 'mcp.sdk';

    public const LIBRARY_SCRIPTS = 'mcp.library_scripts';

    /**
     * Bind the current MCP request for handler injection.
     *
     * @param \Cake\Core\ContainerInterface $container Container instance
     * @param \Crustum\Mcp\Request $request MCP request
     * @return void
     */
    public static function bindRequest(ContainerInterface $container, Request $request): void
    {
        self::bind($container, self::REQUEST, $request);
        self::bind($container, Request::class, $request);
    }

    /**
     * Bind a scoped container value, replacing any existing definition.
     *
     * @param \Cake\Core\ContainerInterface $container Container instance
     * @param string $id Binding identifier
     * @param mixed $instance Bound instance
     * @return void
     */
    public static function bind(ContainerInterface $container, string $id, mixed $instance): void
    {
        $container->add($id, $instance, true);
    }

    /**
     * Remove MCP request bindings after an RPC handler completes.
     *
     * @param \Cake\Core\ContainerInterface $container Container instance
     * @return void
     */
    public static function releaseRequest(ContainerInterface $container): void
    {
        self::release($container, self::REQUEST, Request::class);
    }

    /**
     * Remove scoped bindings after an RPC handler completes.
     *
     * @param \Cake\Core\ContainerInterface $container Container instance
     * @param string ...$ids Binding identifiers
     * @return void
     */
    public static function release(ContainerInterface $container, string ...$ids): void
    {
        if (!$container instanceof Container) {
            return;
        }

        $definitions = self::definitions($container);

        if (!$definitions instanceof DefinitionAggregate) {
            return;
        }

        foreach ($ids as $id) {
            if ($definitions->has($id)) {
                $definitions->remove($id);
            }
        }
    }

    /**
     * Get the League definition aggregate from a Cake container.
     *
     * @param \Cake\Core\Container $container Container instance
     * @return \League\Container\Definition\DefinitionAggregate|null
     */
    protected static function definitions(Container $container): ?DefinitionAggregate
    {
        $property = new ReflectionProperty(Container::class, 'definitions');

        $definitions = $property->getValue($container);

        return $definitions instanceof DefinitionAggregate ? $definitions : null;
    }
}
