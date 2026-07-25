<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server;

use Crustum\Mcp\Server\Annotations\Annotation;
use Crustum\Mcp\Server\Attributes\MimeType;
use Crustum\Mcp\Server\Attributes\Uri;
use Crustum\Mcp\Server\Contracts\HasUriTemplate;
use Crustum\Mcp\Server\Trait\HasAnnotationsTrait;
use Crustum\Mcp\Support\Str;
use ReflectionClass;

/**
 * Base MCP resource primitive.
 */
abstract class Resource extends Primitive
{
    use HasAnnotationsTrait;

    /**
     * Resource URI override.
     *
     * @var string
     */
    protected string $uri = '';

    /**
     * Resource MIME type override.
     *
     * @var string
     */
    protected string $mimeType = '';

    /**
     * Default URI scheme for generated resource URIs.
     *
     * @var string
     */
    protected string $defaultUriScheme = 'file';

    /**
     * Get the resource URI.
     *
     * @return string
     */
    public function uri(): string
    {
        if ($this instanceof HasUriTemplate) {
            return (string)$this->uriTemplate();
        }

        $attribute = $this->resolveAttribute(Uri::class);

        return $attribute !== null
            ? $attribute->value
            : ($this->uri !== '' ? $this->uri : $this->defaultUriScheme . '://resources/' . Str::kebab((new ReflectionClass($this))->getShortName()));
    }

    /**
     * Get the resource MIME type.
     *
     * @return string
     */
    public function mimeType(): string
    {
        $attribute = $this->resolveAttribute(MimeType::class);

        return $attribute !== null
            ? $attribute->value
            : ($this->mimeType !== '' ? $this->mimeType : 'text/plain');
    }

    /**
     * Get the JSON-RPC method call payload for the resource.
     *
     * @return array<string, mixed>
     */
    public function toMethodCall(): array
    {
        return ['uri' => $this->uri()];
    }

    /**
     * Convert the resource to an array.
     *
     * @return array{
     *     name: string,
     *     title: string,
     *     description: string,
     *     uri?: string,
     *     uriTemplate?: string,
     *     mimeType: string,
     *     _meta?: array<string, mixed>
     * }
     */
    public function toArray(): array
    {
        $annotations = $this->annotations();

        $data = [
            'name' => $this->name(),
            'title' => $this->title(),
            'description' => $this->description(),
            'mimeType' => $this->mimeType(),
        ];

        if ($annotations !== []) {
            $data['annotations'] = $annotations;
        }

        if ($this instanceof HasUriTemplate) {
            $data['uriTemplate'] = (string)$this->uriTemplate();
        } else {
            $data['uri'] = $this->uri();
        }

        return $this->mergeMeta($this->mergeIcons($data));
    }

    /**
     * Get annotation classes allowed on resources.
     *
     * @return array<int, class-string>
     */
    protected function allowedAnnotations(): array
    {
        return [
            Annotation::class,
        ];
    }
}
