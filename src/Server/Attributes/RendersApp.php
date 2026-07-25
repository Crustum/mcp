<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Attributes;

use Attribute;
use Crustum\Mcp\Server\Ui\Enums\Visibility;

/**
 * MCP app rendering attribute for resources.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class RendersApp
{
    /**
     * @param class-string $resource App resource class name
     * @param array<int, \Crustum\Mcp\Server\Ui\Enums\Visibility> $visibility UI visibility scopes
     */
    public function __construct(
        public string $resource,
        public array $visibility = [Visibility::Model, Visibility::App],
    ) {
    }
}
