<?php
declare(strict_types=1);

namespace Crustum\Mcp\Trait;

use BackedEnum;
use Cake\Chronos\Chronos;
use Cake\Collection\Collection;
use Cake\Utility\Hash;
use Closure;
use Crustum\Mcp\Support\Str;
use Crustum\Mcp\Support\Stringable;
use stdClass;

/**
 * Provides typed data accessors for MCP request-like objects.
 */
trait InteractsWithDataTrait
{
    /**
     * Retrieve all data from the instance.
     *
     * @param mixed $keys Keys to retrieve
     * @return array<string, mixed>
     */
    abstract public function all(mixed $keys = null): array;

    /**
     * Retrieve data from the instance.
     *
     * @param string|null $key Data key
     * @param mixed $default Default value
     * @return mixed
     */
    abstract protected function data(?string $key = null, mixed $default = null): mixed;

    /**
     * Determine if the data contains a given key.
     *
     * @param array<int, string>|string $key Data key or keys
     * @return bool
     */
    public function exists(string|array $key): bool
    {
        return $this->has($key);
    }

    /**
     * Determine if the data contains a given key.
     *
     * @param mixed $key Data key or keys
     * @return bool
     */
    public function has(mixed $key): bool
    {
        $keys = is_array($key) ? $key : func_get_args();
        $data = $this->all();

        return array_all($keys, static fn(string $value): bool => Hash::check($data, $value));
    }

    /**
     * Determine if the instance contains any of the given keys.
     *
     * @param array<int, string>|string $keys Data keys
     * @return bool
     */
    public function hasAny(string|array $keys): bool
    {
        $keys = is_array($keys) ? $keys : func_get_args();
        $data = $this->all();

        return array_any($keys, fn(string $key): bool => Hash::check($data, $key));
    }

    /**
     * Apply the callback if the instance contains the given key.
     *
     * @param string $key Data key
     * @param callable(mixed): mixed $callback Callback to invoke
     * @param callable(): mixed|null $default Default callback
     * @return mixed
     */
    public function whenHas(string $key, callable $callback, ?callable $default = null): mixed
    {
        if ($this->has($key)) {
            return $callback(Hash::get($this->all(), $key)) ?: $this;
        }

        if ($default !== null) {
            return $default();
        }

        return $this;
    }

    /**
     * Determine if the instance contains a non-empty value for the given key.
     *
     * @param mixed $key Data key or keys
     * @return bool
     */
    public function filled(mixed $key): bool
    {
        $keys = is_array($key) ? $key : func_get_args();

        return array_all($keys, fn(string $value): bool => !$this->isEmptyString($value));
    }

    /**
     * Determine if the instance contains an empty value for the given key.
     *
     * @param mixed $key Data key or keys
     * @return bool
     */
    public function isNotFilled(mixed $key): bool
    {
        $keys = is_array($key) ? $key : func_get_args();

        return array_all($keys, fn(string $value): bool => $this->isEmptyString($value));
    }

    /**
     * Determine if the instance contains a non-empty value for any of the given keys.
     *
     * @param mixed $keys Data keys
     * @return bool
     */
    public function anyFilled(mixed $keys): bool
    {
        $keys = is_array($keys) ? $keys : func_get_args();

        return array_any($keys, fn(string $key): bool => $this->filled($key));
    }

    /**
     * Apply the callback if the instance contains a non-empty value for the given key.
     *
     * @param string $key Data key
     * @param callable(mixed): mixed $callback Callback to invoke
     * @param callable(): mixed|null $default Default callback
     * @return mixed
     */
    public function whenFilled(string $key, callable $callback, ?callable $default = null): mixed
    {
        if ($this->filled($key)) {
            return $callback(Hash::get($this->all(), $key)) ?: $this;
        }

        if ($default !== null) {
            return $default();
        }

        return $this;
    }

    /**
     * Apply the callback if the instance contains a valid enum value for the given key.
     *
     * @template TEnum of \BackedEnum
     * @param string $key Data key
     * @param class-string<TEnum> $enumClass Enum class name
     * @param callable(TEnum): mixed $callback Callback to invoke
     * @param callable(): mixed|null $default Default callback
     * @return mixed
     */
    public function whenEnum(string $key, string $enumClass, callable $callback, ?callable $default = null): mixed
    {
        if ($this->filled($key) && $this->isBackedEnum($enumClass)) {
            $value = $enumClass::tryFrom(Hash::get($this->all(), $key));

            if ($value !== null) {
                return $callback($value) ?: $this;
            }
        }

        if ($default !== null) {
            return $default();
        }

        return $this;
    }

    /**
     * Determine if the instance is missing a given key.
     *
     * @param mixed $key Data key or keys
     * @return bool
     */
    public function missing(mixed $key): bool
    {
        $keys = is_array($key) ? $key : func_get_args();

        return !$this->has($keys);
    }

    /**
     * Apply the callback if the instance is missing the given key.
     *
     * @param string $key Data key
     * @param callable(mixed): mixed $callback Callback to invoke
     * @param callable(): mixed|null $default Default callback
     * @return mixed
     */
    public function whenMissing(string $key, callable $callback, ?callable $default = null): mixed
    {
        if ($this->missing($key)) {
            return $callback(Hash::get($this->all(), $key)) ?: $this;
        }

        if ($default !== null) {
            return $default();
        }

        return $this;
    }

    /**
     * Determine if the given key is an empty string for "filled".
     *
     * @param string $key Data key
     * @return bool
     */
    protected function isEmptyString(string $key): bool
    {
        $value = $this->data($key);

        return !is_bool($value) && !is_array($value) && trim((string)$value) === '';
    }

    /**
     * Retrieve data from the instance as a stringable instance.
     *
     * @param string $key Data key
     * @param mixed $default Default value
     * @return \Crustum\Mcp\Support\Stringable
     */
    public function str(string $key, mixed $default = null): Stringable
    {
        return $this->string($key, $default);
    }

    /**
     * Retrieve data from the instance as a stringable instance.
     *
     * @param string $key Data key
     * @param mixed $default Default value
     * @return \Crustum\Mcp\Support\Stringable
     */
    public function string(string $key, mixed $default = null): Stringable
    {
        return Str::of($this->data($key, $default));
    }

    /**
     * Retrieve data as a boolean value.
     *
     * @param string|null $key Data key
     * @param bool $default Default value
     * @return bool
     */
    public function boolean(?string $key = null, bool $default = false): bool
    {
        return filter_var($this->data($key, $default), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Retrieve data as an integer value.
     *
     * @param string $key Data key
     * @param int $default Default value
     * @return int
     */
    public function integer(string $key, int $default = 0): int
    {
        return (int)$this->data($key, $default);
    }

    /**
     * Retrieve data as a float value.
     *
     * @param string $key Data key
     * @param float $default Default value
     * @return float
     */
    public function float(string $key, float $default = 0.0): float
    {
        return (float)$this->data($key, $default);
    }

    /**
     * Retrieve data clamped between min and max values.
     *
     * @param string $key Data key
     * @param float|int $min Minimum value
     * @param float|int $max Maximum value
     * @param float|int $default Default value
     * @return float|int
     */
    public function clamp(string $key, int|float $min, int|float $max, int|float $default = 0): int|float
    {
        $value = $this->data($key, $default);

        return max($min, min($max, $value));
    }

    /**
     * Retrieve data from the instance as a Chronos instance.
     *
     * @param string $key Data key
     * @param string|null $format Date format
     * @param string|null $timezone Timezone name
     * @return \Cake\Chronos\Chronos|null
     */
    public function date(string $key, ?string $format = null, ?string $timezone = null): ?Chronos
    {
        if ($this->isNotFilled($key)) {
            return null;
        }

        $value = $this->data($key);

        if ($format === null) {
            return Chronos::parse($value, $timezone);
        }

        return Chronos::createFromFormat($format, (string)$value, $timezone);
    }

    /**
     * Retrieve data from the instance as an enum.
     *
     * @template TEnum of \BackedEnum
     * @param string $key Data key
     * @param class-string<TEnum> $enumClass Enum class name
     * @param TEnum|null $default Default enum value
     * @return TEnum|null
     */
    public function enum(string $key, string $enumClass, mixed $default = null): mixed
    {
        if ($this->isNotFilled($key) || !$this->isBackedEnum($enumClass)) {
            return $this->resolveValue($default);
        }

        return $enumClass::tryFrom($this->data($key)) ?? $this->resolveValue($default);
    }

    /**
     * Retrieve data from the instance as an array of enums.
     *
     * @template TEnum of \BackedEnum
     * @param string $key Data key
     * @param class-string<TEnum> $enumClass Enum class name
     * @return array<int, TEnum>
     */
    public function enums(string $key, string $enumClass): array
    {
        if ($this->isNotFilled($key) || !$this->isBackedEnum($enumClass)) {
            return [];
        }

        $values = [];

        foreach ($this->collect($key) as $value) {
            $enum = $enumClass::tryFrom($value);

            if ($enum !== null) {
                $values[] = $enum;
            }
        }

        return $values;
    }

    /**
     * Determine if the given enum class is backed.
     *
     * @param class-string $enumClass Enum class name
     * @return bool
     */
    protected function isBackedEnum(string $enumClass): bool
    {
        return is_a($enumClass, BackedEnum::class, true);
    }

    /**
     * Retrieve data from the instance as an array.
     *
     * @param array<int, string>|string|null $key Data key or keys
     * @return array<string, mixed>
     */
    public function array(array|string|null $key = null): array
    {
        return (array)(is_array($key) ? $this->only($key) : $this->data($key));
    }

    /**
     * Retrieve data from the instance as a collection.
     *
     * @param array<int, string>|string|null $key Data key or keys
     * @return \Cake\Collection\Collection
     */
    public function collect(array|string|null $key = null): Collection
    {
        return new Collection(is_array($key) ? $this->only($key) : $this->data($key));
    }

    /**
     * Get a subset containing the provided keys with values from the instance data.
     *
     * @param mixed $keys Keys to retrieve
     * @return array<string, mixed>
     */
    public function only(mixed $keys): array
    {
        $results = [];
        $data = $this->all();
        $placeholder = new stdClass();

        foreach (is_array($keys) ? $keys : func_get_args() as $key) {
            $value = Hash::get($data, (string)$key, $placeholder);

            if ($value !== $placeholder) {
                $results = Hash::insert($results, (string)$key, $value);
            }
        }

        return $results;
    }

    /**
     * Get all of the data except for a specified array of items.
     *
     * @param mixed $keys Keys to exclude
     * @return array<string, mixed>
     */
    public function except(mixed $keys): array
    {
        $keys = is_array($keys) ? $keys : func_get_args();
        $results = $this->all();

        foreach ($keys as $key) {
            $results = Hash::remove($results, (string)$key);
        }

        return $results;
    }

    /**
     * Resolve a value or closure default.
     *
     * @param mixed $value Value or closure
     * @return mixed
     */
    protected function resolveValue(mixed $value): mixed
    {
        return $value instanceof Closure ? $value() : $value;
    }
}
