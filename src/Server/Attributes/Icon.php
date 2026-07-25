<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Attributes;

use Attribute;
use Crustum\Mcp\Enums\IconTheme;
use Crustum\Mcp\Schema\Icon as IconValue;

/**
 * MCP server icon attribute.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Icon
{
    /**
     * @param string $src Icon source URL or path
     * @param string|null $mimeType Icon MIME type
     * @param list<string> $sizes Supported icon sizes
     * @param \Crustum\Mcp\Enums\IconTheme|null $theme Icon theme
     */
    public function __construct(
        public string $src,
        public ?string $mimeType = null,
        public array $sizes = [],
        public ?IconTheme $theme = null,
    ) {
    }

    /**
     * Convert the attribute to a schema icon value.
     *
     * @return \Crustum\Mcp\Schema\Icon
     */
    public function toIcon(): IconValue
    {
        return IconValue::from($this->src, $this->mimeType, $this->sizes, $this->theme);
    }
}
