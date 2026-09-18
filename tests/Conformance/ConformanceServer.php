<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Conformance;

use Crustum\Mcp\Server;
use Crustum\Mcp\Server\Conformance\Prompts\PromptWithArguments;
use Crustum\Mcp\Server\Conformance\Prompts\PromptWithEmbeddedResource;
use Crustum\Mcp\Server\Conformance\Prompts\PromptWithImage;
use Crustum\Mcp\Server\Conformance\Prompts\SimplePrompt;
use Crustum\Mcp\Server\Conformance\Resources\StaticBinaryResource;
use Crustum\Mcp\Server\Conformance\Resources\StaticTextResource;
use Crustum\Mcp\Server\Conformance\Resources\TemplateResource;
use Crustum\Mcp\Server\Conformance\Resources\WatchedResource;
use Crustum\Mcp\Server\Conformance\Tools\AudioContentTool;
use Crustum\Mcp\Server\Conformance\Tools\EmbeddedResourceTool;
use Crustum\Mcp\Server\Conformance\Tools\ErrorHandlingTool;
use Crustum\Mcp\Server\Conformance\Tools\ImageContentTool;
use Crustum\Mcp\Server\Conformance\Tools\MultipleContentTypesTool;
use Crustum\Mcp\Server\Conformance\Tools\SimpleTextTool;

class ConformanceServer extends Server
{
    protected string $name = 'mcp-conformance-test-server';

    protected string $version = '1.0.0';

    protected array $supportedProtocolVersion = [
        '2026-07-28',
        '2025-11-25',
        '2025-06-18',
    ];

    public array $tools = [
        SimpleTextTool::class,
        ImageContentTool::class,
        AudioContentTool::class,
        EmbeddedResourceTool::class,
        MultipleContentTypesTool::class,
        ErrorHandlingTool::class,
    ];

    public array $resources = [
        StaticTextResource::class,
        StaticBinaryResource::class,
        TemplateResource::class,
        WatchedResource::class,
    ];

    public array $prompts = [
        SimplePrompt::class,
        PromptWithArguments::class,
        PromptWithEmbeddedResource::class,
        PromptWithImage::class,
    ];
}
