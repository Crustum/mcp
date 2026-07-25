<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Trait;

use Crustum\Mcp\Server\Contracts\Annotation as AnnotationContract;
use InvalidArgumentException;
use ReflectionAttribute;
use ReflectionClass;

/**
 * Resolves MCP annotation attributes declared on server primitives.
 */
trait HasAnnotationsTrait
{
    /**
     * Resolve annotation attributes into an MCP annotations array.
     *
     * @return array<string, mixed>
     */
    public function annotations(): array
    {
        $reflection = new ReflectionClass($this);
        $annotations = [];

        foreach ($reflection->getAttributes() as $attributeReflection) {
            $attribute = $this->instantiateAnnotationAttribute($attributeReflection);

            if ($attribute === null) {
                continue;
            }

            $this->validateAnnotationUsage($attribute);
            $annotations[$attribute->key()] = $attribute->value; // @phpstan-ignore property.notFound
        }

        return $annotations;
    }

    /**
     * Instantiate an annotation attribute when it implements the annotation contract.
     *
     * @param \ReflectionAttribute<object> $attributeReflection Attribute reflection
     * @return \Crustum\Mcp\Server\Contracts\Annotation|null
     */
    private function instantiateAnnotationAttribute(ReflectionAttribute $attributeReflection): ?AnnotationContract
    {
        $attribute = $attributeReflection->newInstance();

        if (!$attribute instanceof AnnotationContract) {
            return null;
        }

        return $attribute;
    }

    /**
     * Ensure the annotation is allowed on the current primitive.
     *
     * @param \Crustum\Mcp\Server\Contracts\Annotation $attribute Annotation instance
     * @return void
     */
    private function validateAnnotationUsage(AnnotationContract $attribute): void
    {
        $allowedAnnotations = $this->allowedAnnotations();

        foreach ($allowedAnnotations as $allowedAnnotationClass) {
            if ($attribute instanceof $allowedAnnotationClass) {
                return;
            }
        }

        $allowedClasses = $allowedAnnotations === []
            ? 'none'
            : implode(', ', $allowedAnnotations);

        throw new InvalidArgumentException(
            sprintf(
                'Annotation [%s] cannot be used on [%s]. Allowed annotation types: [%s]',
                $attribute::class,
                $this::class,
                $allowedClasses,
            ),
        );
    }

    /**
     * Get annotation classes allowed on the current primitive.
     *
     * @return array<int, class-string>
     */
    protected function allowedAnnotations(): array
    {
        return [];
    }
}
