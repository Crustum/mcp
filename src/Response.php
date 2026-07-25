<?php
declare(strict_types=1);

namespace Crustum\Mcp;

use Cake\View\View;
use Crustum\Mcp\Enums\Role;
use Crustum\Mcp\Server\Content\Audio;
use Crustum\Mcp\Server\Content\Blob;
use Crustum\Mcp\Server\Content\Image;
use Crustum\Mcp\Server\Content\Notification;
use Crustum\Mcp\Server\Content\ResourceLink as ResourceLinkContent;
use Crustum\Mcp\Server\Content\Text;
use Crustum\Mcp\Server\Contracts\Content;
use Crustum\Mcp\Server\Resource;
use Crustum\Mcp\Support\ContainerRegistry;
use Crustum\Mcp\Trait\ConditionableTrait;
use Crustum\Mcp\Trait\MacroableTrait;
use InvalidArgumentException;
use JsonException;
use function Cake\Core\pluginSplit;

/**
 * MCP tool, prompt, and resource response wrapper.
 *
 * @phpstan-consistent-constructor
 */
class Response
{
    use ConditionableTrait;
    use MacroableTrait;

    /**
     * @param \Crustum\Mcp\Server\Contracts\Content $content Response content
     * @param \Crustum\Mcp\Enums\Role $role Message role
     * @param bool $isError Whether the response represents an error
     */
    protected function __construct(
        protected Content $content,
        protected Role $role = Role::User,
        protected bool $isError = false,
    ) {
    }

    /**
     * Create a text response.
     *
     * @param string $text Text content
     * @return static
     */
    public static function text(string $text): static
    {
        return new static(new Text($text));
    }

    /**
     * Create a text response from an HTML file.
     *
     * @param string $path File path (relative paths resolve from ROOT)
     * @return static
     */
    public static function html(string $path): static
    {
        if (!str_starts_with($path, '/') && !preg_match('/^[a-zA-Z]:[\\\\\\/]/', $path)) {
            $path = ROOT . DS . str_replace('/', DS, $path);
        }

        if (!is_file($path)) {
            throw new InvalidArgumentException("File not found at path [{$path}].");
        }

        return static::text((string)file_get_contents($path));
    }

    /**
     * Create a text response from a rendered CakePHP template.
     *
     * @param string $template Template name (for example `Mcp/example` or `Crustum/Mcp.example`)
     * @param array<string, mixed> $data View variables
     * @param array<string, mixed> $mergeData Additional variables merged before $data
     * @param string|false|null $layout Layout name, or `false` for no layout
     * @return static
     */
    public static function view(
        string $template,
        array $data = [],
        array $mergeData = [],
        string|false|null $layout = false,
    ): static {
        $view = new View();

        [$plugin, $templateName] = pluginSplit($template);
        if ($plugin !== null) {
            $view->setPlugin($plugin);
        }

        $view->set(array_merge($mergeData, $data));

        return static::text($view->render($templateName, $layout));
    }

    /**
     * Create a blob response.
     *
     * @param string $content Raw binary content
     * @return static
     */
    public static function blob(string $content): static
    {
        return new static(new Blob($content));
    }

    /**
     * Create an audio response.
     *
     * @param string $data Raw audio bytes
     * @param string $mimeType Audio MIME type
     * @return static
     */
    public static function audio(string $data, string $mimeType = 'audio/wav'): static
    {
        return new static(new Audio($data, $mimeType));
    }

    /**
     * Create an image response.
     *
     * @param string $data Raw image bytes
     * @param string $mimeType Image MIME type
     * @return static
     */
    public static function image(string $data, string $mimeType = 'image/png'): static
    {
        return new static(new Image($data, $mimeType));
    }

    /**
     * Create a JSON-encoded text response.
     *
     * @param mixed $content JSON-serializable content
     * @return static
     */
    public static function json(mixed $content): static
    {
        return static::text(json_encode($content, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Create an error text response.
     *
     * @param string $text Error message
     * @return static
     */
    public static function error(string $text): static
    {
        return new static(new Text($text), isError: true);
    }

    /**
     * Create a notification response.
     *
     * @param string $method Notification method name
     * @param array<string, mixed> $params Notification parameters
     * @return static
     */
    public static function notification(string $method, array $params = []): static
    {
        return new static(new Notification($method, $params));
    }

    /**
     * Create a structured content response factory.
     *
     * @param array<string, mixed> $response Structured content payload
     * @return \Crustum\Mcp\ResponseFactory
     */
    public static function structured(array $response): ResponseFactory
    {
        if ($response === []) {
            throw new InvalidArgumentException('Structured content cannot be empty.');
        }

        try {
            $json = json_encode($response, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $jsonException) {
            throw new InvalidArgumentException(
                "Invalid structured content: {$jsonException->getMessage()}",
                0,
                $jsonException,
            );
        }

        return (new ResponseFactory(static::text($json)))->withStructuredContent($response);
    }

    /**
     * Create a resource link response.
     *
     * @param \Crustum\Mcp\Server\Resource|\Crustum\Mcp\Server\Content\ResourceLink|class-string<\Crustum\Mcp\Server\Resource>|string $uri Resource URI, class, instance, or link
     * @param string|null $name Resource name
     * @param string|null $mimeType Resource MIME type
     * @param string|null $title Resource title
     * @param string|null $description Resource description
     * @param int|null $size Resource size in bytes
     * @param array<string, mixed> $annotations Resource annotations
     * @param list<\Crustum\Mcp\Schema\Icon> $icons Resource icons
     * @return static
     */
    public static function resourceLink(
        string|Resource|ResourceLinkContent $uri,
        ?string $name = null,
        ?string $mimeType = null,
        ?string $title = null,
        ?string $description = null,
        ?int $size = null,
        array $annotations = [],
        array $icons = [],
    ): static {
        if (is_string($uri) && is_subclass_of($uri, Resource::class)) {
            $container = ContainerRegistry::getInstance();
            $uri = $container->has($uri) ? $container->get($uri) : new $uri();
        }

        $link = match (true) {
            $uri instanceof ResourceLinkContent => $uri,
            $uri instanceof Resource => new ResourceLinkContent(
                uri: $uri->uri(),
                name: $name ?? $uri->name(),
                mimeType: $mimeType ?? $uri->mimeType(),
                title: $title ?? $uri->title(),
                description: $description ?? $uri->description(),
                size: $size,
                annotations: array_merge($uri->annotations(), $annotations),
                icons: $icons === [] ? $uri->resolvedIcons() : $icons,
            ),
            default => new ResourceLinkContent(
                uri: $uri,
                name: $name ?? throw new InvalidArgumentException('Resource link name is required when using a URI string.'),
                mimeType: $mimeType,
                title: $title,
                description: $description,
                size: $size,
                annotations: $annotations,
                icons: $icons,
            ),
        };

        return new static($link);
    }

    /**
     * Wrap one or more responses in a response factory.
     *
     * @param self|array<int, self> $responses Response instances
     * @return \Crustum\Mcp\ResponseFactory
     */
    public static function make(self|array $responses): ResponseFactory
    {
        return new ResponseFactory($responses);
    }

    /**
     * Get the underlying content object.
     *
     * @return \Crustum\Mcp\Server\Contracts\Content
     */
    public function content(): Content
    {
        return $this->content;
    }

    /**
     * Attach metadata to the response content.
     *
     * @param array<string, mixed>|string $meta Metadata array or key name
     * @param mixed $value Metadata value when using key/value signature
     * @return static
     */
    public function withMeta(array|string $meta, mixed $value = null): static
    {
        $this->content->setMeta($meta, $value);

        return $this;
    }

    /**
     * Mark the response as an assistant message.
     *
     * @return static
     */
    public function asAssistant(): static
    {
        return new static($this->content, Role::Assistant, $this->isError);
    }

    /**
     * Determine whether the response is a notification.
     *
     * @return bool
     */
    public function isNotification(): bool
    {
        return $this->content instanceof Notification;
    }

    /**
     * Determine whether the response represents an error.
     *
     * @return bool
     */
    public function isError(): bool
    {
        return $this->isError;
    }

    /**
     * Get the response role.
     *
     * @return \Crustum\Mcp\Enums\Role
     */
    public function role(): Role
    {
        return $this->role;
    }
}
