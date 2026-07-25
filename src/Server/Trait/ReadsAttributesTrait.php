<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Trait;

use ReflectionClass;

/**
 * Resolves PHP attributes from the current class and its parents.
 */
trait ReadsAttributesTrait
{
    /**
     * Cached attribute instances keyed by class and attribute name.
     *
     * @var array<string, object|list<object>|null>
     */
    protected static array $attributeCache = [];

    /**
     * Resolve the first matching attribute instance on the class hierarchy.
     *
     * @template T of object
     * @param class-string<T> $attributeClass Attribute class name
     * @return T|null
     */
    protected function resolveAttribute(string $attributeClass): mixed
    {
        $cacheKey = static::class . '@' . $attributeClass;

        if (array_key_exists($cacheKey, static::$attributeCache)) {
            return static::$attributeCache[$cacheKey];
        }

        $reflection = new ReflectionClass($this);

        while (true) {
            $attributes = $reflection->getAttributes($attributeClass);

            if ($attributes !== []) {
                return static::$attributeCache[$cacheKey] = $attributes[0]->newInstance();
            }

            $parentClass = $reflection->getParentClass();

            if ($parentClass === false) {
                break;
            }

            $reflection = $parentClass;
        }

        return static::$attributeCache[$cacheKey] = null;
    }

    /**
     * Resolve all matching attribute instances on the class hierarchy.
     *
     * @template T of object
     * @param class-string<T> $attributeClass Attribute class name
     * @return list<T>
     */
    protected function resolveAttributes(string $attributeClass): array
    {
        $cacheKey = static::class . '@' . $attributeClass . '[]';

        if (array_key_exists($cacheKey, static::$attributeCache)) {
            return static::$attributeCache[$cacheKey];
        }

        $instances = [];
        $reflection = new ReflectionClass($this);

        while (true) {
            foreach ($reflection->getAttributes($attributeClass) as $attribute) {
                $instances[] = $attribute->newInstance();
            }

            $parentClass = $reflection->getParentClass();

            if ($parentClass === false) {
                break;
            }

            $reflection = $parentClass;
        }

        return static::$attributeCache[$cacheKey] = $instances;
    }
}
