<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Trait;

use Crustum\Mcp\Schema\Icon;
use Crustum\Mcp\Server\Attributes\Icon as IconAttribute;

/**
 * Resolves icon attributes and merges them with primitive icon definitions.
 */
trait HasIconsTrait
{
    use ReadsAttributesTrait;

    /**
     * Resolve icons from attributes and the primitive icons() method.
     *
     * @return list<\Crustum\Mcp\Schema\Icon>
     */
    public function resolvedIcons(): array
    {
        $attributeIcons = array_map(
            fn(IconAttribute $icon): Icon => $icon->toIcon(),
            $this->resolveAttributes(IconAttribute::class),
        );

        return [...$attributeIcons, ...$this->icons()];
    }
}
