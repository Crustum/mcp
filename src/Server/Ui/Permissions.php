<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Ui;

use Crustum\Mcp\Contracts\Arrayable;
use stdClass;

/**
 * MCP UI permission configuration.
 *
 * @implements \Crustum\Mcp\Contracts\Arrayable<string, \stdClass>
 */
final class Permissions implements Arrayable
{
    /**
     * Enabled permissions.
     *
     * @var array<int, \Crustum\Mcp\Server\Ui\Enums\Permission>
     */
    protected array $enabled = [];

    /**
     * Create a new permissions builder.
     *
     * @return static
     */
    public static function make(): static
    {
        return new self();
    }

    /**
     * Allow camera access.
     *
     * @return static
     */
    public function camera(): static
    {
        return $this->allow(Enums\Permission::Camera);
    }

    /**
     * Allow microphone access.
     *
     * @return static
     */
    public function microphone(): static
    {
        return $this->allow(Enums\Permission::Microphone);
    }

    /**
     * Allow geolocation access.
     *
     * @return static
     */
    public function geolocation(): static
    {
        return $this->allow(Enums\Permission::Geolocation);
    }

    /**
     * Allow clipboard write access.
     *
     * @return static
     */
    public function clipboardWrite(): static
    {
        return $this->allow(Enums\Permission::ClipboardWrite);
    }

    /**
     * Allow one or more permissions.
     *
     * @param \Crustum\Mcp\Server\Ui\Enums\Permission ...$permissions Permissions to allow
     * @return static
     */
    public function allow(Enums\Permission ...$permissions): static
    {
        array_push($this->enabled, ...$permissions);

        return $this;
    }

    /**
     * Get permissions as an array.
     *
     * @return array<string, \stdClass>
     */
    public function toArray(): array
    {
        $result = [];

        foreach ($this->enabled as $permission) {
            $result[$permission->value] = new stdClass();
        }

        return $result;
    }
}
