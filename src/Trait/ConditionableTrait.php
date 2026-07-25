<?php
declare(strict_types=1);

namespace Crustum\Mcp\Trait;

/**
 * Adds conditional callback helpers to MCP objects.
 */
trait ConditionableTrait
{
    /**
     * Apply the callback when the condition is truthy.
     *
     * @param mixed $value Condition value
     * @param callable(static, mixed): mixed $callback Callback to invoke
     * @param callable(static, mixed): mixed|null $default Default callback
     * @return mixed
     */
    public function when(mixed $value, callable $callback, ?callable $default = null): mixed
    {
        if ($value) {
            return $callback($this, $value) ?: $this;
        }

        if ($default !== null) {
            return $default($this, $value) ?: $this;
        }

        return $this;
    }

    /**
     * Apply the callback when the condition is falsy.
     *
     * @param mixed $value Condition value
     * @param callable(static, mixed): mixed $callback Callback to invoke
     * @param callable(static, mixed): mixed|null $default Default callback
     * @return mixed
     */
    public function unless(mixed $value, callable $callback, ?callable $default = null): mixed
    {
        return $this->when(!$value, $callback, $default);
    }
}
