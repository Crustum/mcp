<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server;

use Crustum\JsonSchema\Contracts\JsonSchema;
use Crustum\JsonSchema\JsonSchema as JsonSchemaFactory;
use Crustum\Mcp\Server\Attributes\RendersApp;
use Crustum\Mcp\Server\Tools\Annotations\ToolAnnotation;
use Crustum\Mcp\Server\Trait\HasAnnotationsTrait;
use Crustum\Mcp\Server\Ui\Enums\Visibility;

/**
 * Base MCP tool primitive.
 */
abstract class Tool extends Primitive
{
    use HasAnnotationsTrait;

    /**
     * Define the input schema for this tool.
     *
     * @param \Crustum\JsonSchema\Contracts\JsonSchema $schema JSON schema builder
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    /**
     * Define the output schema for this tool's results.
     *
     * @param \Crustum\JsonSchema\Contracts\JsonSchema $schema JSON schema builder
     * @return array<string, mixed>
     */
    public function outputSchema(JsonSchema $schema): array
    {
        return [];
    }

    /**
     * Get the JSON-RPC method call payload for the tool.
     *
     * @return array<string, mixed>
     */
    public function toMethodCall(): array
    {
        return ['name' => $this->name()];
    }

    /**
     * Get the tool's array representation.
     *
     * @return array{
     *     name: string,
     *     title?: string|null,
     *     description?: string|null,
     *     inputSchema?: array<string, mixed>,
     *     outputSchema?: array<string, mixed>,
     *     annotations?: array<string, mixed>|object,
     *     _meta?: array<string, mixed>
     * }
     */
    public function toArray(): array
    {
        $annotations = $this->annotations();

        $schema = JsonSchemaFactory::object(
            $this->schema(...),
        )->toArray();

        $outputSchema = JsonSchemaFactory::object(
            $this->outputSchema(...),
        )->toArray();

        $schema['properties'] ??= (object)[];

        $result = [
            'name' => $this->name(),
            'title' => $this->title(),
            'description' => $this->description(),
            'inputSchema' => $schema,
            'annotations' => $annotations === [] ? (object)[] : $annotations,
        ];

        if (isset($outputSchema['properties'])) {
            $result['outputSchema'] = $outputSchema;
        }

        $rendersApp = $this->resolveAttribute(RendersApp::class);

        if ($rendersApp !== null) {
            /** @var \Crustum\Mcp\Server\Resource $appResource */
            $appResource = new $rendersApp->resource();

            $this->setMeta('ui', [
                'resourceUri' => $appResource->uri(),
                'visibility' => array_map(fn(Visibility $visibility): string => $visibility->value, $rendersApp->visibility),
            ]);
        }

        return $this->mergeMeta($this->mergeIcons($result));
    }

    /**
     * Get annotation classes allowed on tools.
     *
     * @return array<int, class-string>
     */
    protected function allowedAnnotations(): array
    {
        return [
            ToolAnnotation::class,
        ];
    }
}
