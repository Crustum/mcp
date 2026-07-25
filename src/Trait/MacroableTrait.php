<?php
declare(strict_types=1);

namespace Crustum\Mcp\Trait;

use BadMethodCallException;
use Closure;

/**
 * Adds macro support to MCP objects.
 */
trait MacroableTrait
{
    /**
     * Registered macro callbacks.
     *
     * @var array<string, callable|object|string>
     */
    protected static array $macros = [];

    /**
     * Register a macro on the class.
     *
     * @param string $name Macro name
     * @param callable|object|string $macro Macro callback or mixin
     * @return void
     */
    public static function macro(string $name, callable|object|string $macro): void
    {
        static::$macros[$name] = $macro;
    }

    /**
     * Mix another object into the class as macros.
     *
     * @param object $mixin Object providing macro methods
     * @return void
     */
    public static function mixin(object $mixin): void
    {
        $methods = get_class_methods($mixin);

        foreach ($methods as $method) {
            static::macro($method, [$mixin, $method]);
        }
    }

    /**
     * Determine whether a macro is registered.
     *
     * @param string $name Macro name
     * @return bool
     */
    public static function hasMacro(string $name): bool
    {
        return isset(static::$macros[$name]);
    }

    /**
     * Remove all registered macros.
     *
     * @return void
     */
    public static function flushMacros(): void
    {
        static::$macros = [];
    }

    /**
     * Dynamically handle macro calls on the instance.
     *
     * @param string $method Method name
     * @param array<int, mixed> $parameters Method parameters
     * @return mixed
     */
    public function __call(string $method, array $parameters): mixed
    {
        if (!static::hasMacro($method)) {
            throw new BadMethodCallException(sprintf(
                'Method %s::%s does not exist.',
                static::class,
                $method,
            ));
        }

        $macro = static::$macros[$method];

        if ($macro instanceof Closure) {
            $bound = $macro->bindTo($this, static::class);

            return $bound(...$parameters);
        }

        return $macro(...$parameters);
    }

    /**
     * Dynamically handle macro calls on the class.
     *
     * @param string $method Method name
     * @param array<int, mixed> $parameters Method parameters
     * @return mixed
     */
    public static function __callStatic(string $method, array $parameters): mixed
    {
        if (!static::hasMacro($method)) {
            throw new BadMethodCallException(sprintf(
                'Method %s::%s does not exist.',
                static::class,
                $method,
            ));
        }

        $macro = static::$macros[$method];

        return $macro(...$parameters);
    }
}
