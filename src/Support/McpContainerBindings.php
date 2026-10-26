<?php
declare(strict_types=1);

namespace Crustum\Mcp\Support;

use Cake\Container\Container;
use Cake\Container\ContainerInterface;
use Cake\Container\Definition\Definition;
use Cake\Container\Definition\DefinitionAggregate;
use Crustum\Mcp\Request;
use ReflectionProperty;

/**
 * Per-RPC scoped bindings for the Cake container.
 *
 * Cake's DefinitionAggregate overwrites existing definitions on add(), so
 * rebinding per-request values needs no overwrite flag.
 */
final class McpContainerBindings
{
    public const string REQUEST = 'mcp.request';

    public const string SDK = 'mcp.sdk';

    public const string LIBRARY_SCRIPTS = 'mcp.library_scripts';

    /**
     * Bind the current MCP request for handler injection.
     *
     * @param \Cake\Container\ContainerInterface $container Container instance
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
     * @param \Cake\Container\ContainerInterface $container Container instance
     * @param string $id Binding identifier
     * @param mixed $instance Bound instance
     * @return void
     */
    public static function bind(ContainerInterface $container, string $id, mixed $instance): void
    {
        $container->addShared($id, $instance);
    }

    /**
     * Remove MCP request bindings after an RPC handler completes.
     *
     * @param \Cake\Container\ContainerInterface $container Container instance
     * @return void
     */
    public static function releaseRequest(ContainerInterface $container): void
    {
        self::release($container, self::REQUEST, Request::class);
    }

    /**
     * Remove scoped bindings after an RPC handler completes.
     *
     * @param \Cake\Container\ContainerInterface $container Container instance
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

        $property = new ReflectionProperty(DefinitionAggregate::class, 'definitions');
        /** @var array<string, mixed> $map */
        $map = $property->getValue($definitions);

        foreach ($ids as $id) {
            unset($map[Definition::normaliseAlias($id)]);
        }

        $property->setValue($definitions, $map);
    }

    /**
     * Get the definition aggregate from a Cake container.
     *
     * @param \Cake\Container\Container $container Container instance
     * @return \Cake\Container\Definition\DefinitionAggregate|null
     */
    protected static function definitions(Container $container): ?DefinitionAggregate
    {
        $property = new ReflectionProperty(Container::class, 'definitions');

        $definitions = $property->getValue($container);

        return $definitions instanceof DefinitionAggregate ? $definitions : null;
    }
}
