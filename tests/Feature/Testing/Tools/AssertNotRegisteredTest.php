<?php
declare(strict_types=1);

use Crustum\Mcp\Test\Fixtures\ExampleServer;
use Crustum\Mcp\Test\Fixtures\SayHiTool;
use Crustum\Mcp\Test\Fixtures\UriTemplateUserFileResource;

it('asserts tool is not registered', function (): void {
    ExampleServer::tools()
        ->assertNotRegistered(UriTemplateUserFileResource::class);
});

it('fails when tool is registered', function (): void {
    ExampleServer::tools()
        ->assertNotRegistered(SayHiTool::class);
})->throws(\PHPUnit\Framework\AssertionFailedError::class);
