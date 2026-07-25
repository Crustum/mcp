<?php
declare(strict_types=1);

namespace Crustum\Mcp\Test\Fixtures;

use Crustum\Mcp\Enums\Role;
use Crustum\Mcp\Server\Annotations\Audience;
use Crustum\Mcp\Server\Annotations\LastModified;
use Crustum\Mcp\Server\Annotations\Priority;
use Crustum\Mcp\Server\Resource;

#[Audience([Role::User])]
#[Priority(0.7)]
#[LastModified('2026-05-01T00:00:00Z')]
class AnnotatedResource extends Resource
{
    protected string $uri = 'file://resources/annotated';

    protected string $mimeType = 'text/plain';

    public function handle(): string
    {
        return 'annotated content';
    }
}
