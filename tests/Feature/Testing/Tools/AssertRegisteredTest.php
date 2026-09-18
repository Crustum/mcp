<?php
declare(strict_types=1);

use Crustum\Mcp\Test\Fixtures\ExampleServer;
use Crustum\Mcp\Test\Fixtures\SayHiTool;
use Crustum\Mcp\Test\Fixtures\UriTemplateUserFileResource;

it('asserts registered tools', function (): void {
    ExampleServer::tools()
        ->assertRegistered([SayHiTool::class]);
});

it('asserts registered tools by class name string', function (): void {
    ExampleServer::tools()
        ->assertRegistered(SayHiTool::class);
});

it('fails when tool is not registered', function (): void {
    ExampleServer::tools()
        ->assertRegistered(UriTemplateUserFileResource::class);
})->throws(\PHPUnit\Framework\AssertionFailedError::class);
