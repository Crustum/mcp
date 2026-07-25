<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Annotations;

use Attribute;
use Crustum\Mcp\Enums\Role;
use InvalidArgumentException;

/**
 * MCP audience annotation attribute.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Audience extends Annotation
{
    /**
     * Audience role values.
     *
     * @var array<int, string>
     */
    public array $value;

    /**
     * @param \Crustum\Mcp\Enums\Role|array<int, mixed> $roles Audience roles
     */
    public function __construct(Role|array $roles)
    {
        $roles = is_array($roles) ? $roles : [$roles];

        foreach ($roles as $role) {
            if (!$role instanceof Role) {
                throw new InvalidArgumentException(
                    'All values of ' . Audience::class . ' attributes must be instances of ' . Role::class,
                );
            }
        }

        $this->value = array_map(fn(Role $role): string => $role->value, $roles);
    }

    /**
     * Get the MCP annotation key.
     *
     * @return string
     */
    public function key(): string
    {
        return 'audience';
    }
}
