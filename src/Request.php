<?php
declare(strict_types=1);

namespace Crustum\Mcp;

use Cake\Validation\Validator;
use Crustum\Mcp\Contracts\Arrayable;
use Crustum\Mcp\Support\ValidationMessages;
use Crustum\Mcp\Trait\ConditionableTrait;
use Crustum\Mcp\Trait\InteractsWithDataTrait;
use Crustum\Mcp\Trait\MacroableTrait;

/**
 * MCP tool, resource, and prompt request payload.
 *
 * @implements \Crustum\Mcp\Contracts\Arrayable<string, mixed>
 */
class Request implements Arrayable
{
    use ConditionableTrait;
    use InteractsWithDataTrait;
    use MacroableTrait;

    /**
     * Request arguments.
     *
     * @var array<string, mixed>
     */
    protected array $arguments = [];

    /**
     * MCP session identifier.
     *
     * @var string|null
     */
    protected ?string $sessionId = null;

    /**
     * Request metadata.
     *
     * @var array<string, mixed>|null
     */
    protected ?array $meta = null;

    /**
     * Resource URI.
     *
     * @var string|null
     */
    protected ?string $uri = null;

    /**
     * Optional principal resolved by host listeners.
     *
     * @var mixed
     */
    protected mixed $identity = null;

    /**
     * Create a new MCP request instance.
     *
     * @param array<string, mixed> $arguments Request arguments
     * @param string|null $sessionId MCP session identifier
     * @param array<string, mixed>|null $meta Request metadata
     * @param string|null $uri Resource URI
     */
    public function __construct(
        array $arguments = [],
        ?string $sessionId = null,
        ?array $meta = null,
        ?string $uri = null,
    ) {
        $this->arguments = $arguments;
        $this->sessionId = $sessionId;
        $this->meta = $meta;
        $this->uri = $uri;
    }

    /**
     * Get all or selected request arguments.
     *
     * @param array-key|array<array-key, string>|null $keys Keys to retrieve
     * @return array<string, mixed>
     */
    public function all(mixed $keys = null): array
    {
        if ($keys === null) {
            return $this->data();
        }

        $keyList = is_array($keys) ? $keys : func_get_args();

        return array_intersect_key($this->data(), array_flip($keyList));
    }

    /**
     * Get request argument data.
     *
     * @param string|null $key Argument key
     * @param mixed $default Default value
     * @return mixed
     */
    protected function data(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->arguments;
        }

        return $this->arguments[$key] ?? $default;
    }

    /**
     * Get a request argument value.
     *
     * @param string $key Argument key
     * @param mixed $default Default value
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data($key, $default);
    }

    /**
     * Get the principal resolved for this MCP request.
     *
     * @return mixed
     */
    public function getIdentity(): mixed
    {
        return $this->identity;
    }

    /**
     * Set the principal for this MCP request.
     *
     * @param mixed $identity Resolved principal
     * @return void
     */
    public function setIdentity(mixed $identity): void
    {
        $this->identity = $identity;
    }

    /**
     * Merge additional arguments into the request.
     *
     * @param array<string, mixed> $data Arguments to merge
     * @return static
     */
    public function merge(array $data): static
    {
        $this->arguments = array_merge($this->arguments, $data);

        return $this;
    }

    /**
     * Get the request arguments as an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->arguments;
    }

    /**
     * Validate request arguments with a CakePHP validator.
     *
     * @param \Cake\Validation\Validator $validator Validator instance
     * @return array<string, mixed>
     * @throws \Crustum\Mcp\Exception\ValidationException
     */
    public function validateWith(Validator $validator): array
    {
        return ValidationMessages::validate($this->all(), $validator);
    }

    /**
     * Get the MCP session identifier.
     *
     * @return string|null
     */
    public function sessionId(): ?string
    {
        return $this->sessionId;
    }

    /**
     * Get request metadata.
     *
     * @return array<string, mixed>|null
     */
    public function meta(): ?array
    {
        return $this->meta;
    }

    /**
     * Get the resource URI.
     *
     * @return string|null
     */
    public function uri(): ?string
    {
        return $this->uri;
    }

    /**
     * Set request arguments.
     *
     * @param array<string, mixed> $arguments Request arguments
     * @return void
     */
    public function setArguments(array $arguments): void
    {
        $this->arguments = $arguments;
    }

    /**
     * Set the MCP session identifier.
     *
     * @param string|null $sessionId MCP session identifier
     * @return void
     */
    public function setSessionId(?string $sessionId): void
    {
        $this->sessionId = $sessionId;
    }

    /**
     * Set request metadata.
     *
     * @param array<string, mixed>|null $meta Request metadata
     * @return void
     */
    public function setMeta(?array $meta): void
    {
        $this->meta = $meta;
    }

    /**
     * Set the resource URI.
     *
     * @param string|null $uri Resource URI
     * @return void
     */
    public function setUri(?string $uri): void
    {
        $this->uri = $uri;
    }
}
