<?php
declare(strict_types=1);

use Crustum\Mcp\Enums\Extension;
use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server;
use Crustum\Mcp\Server\AppResource;
use Crustum\Mcp\Server\Resource;
use Crustum\Mcp\Test\Fixtures\ArrayTransport;

it('advertises the ui extension when app resources are registered', function (): void {
    $server = new class (new ArrayTransport()) extends Server
    {
        protected array $resources = [
            AutoDetectAppResource::class,
        ];
    };

    $server->start();

    $extensions = $server->createContext()->serverCapabilities['extensions'];

    expect($extensions)->toHaveKey('io.modelcontextprotocol/ui');
    expect($extensions['io.modelcontextprotocol/ui'])->toEqual((object)[]);
});

it('advertises no extensions when only regular resources are registered', function (): void {
    $server = new class (new ArrayTransport()) extends Server
    {
        protected array $resources = [
            RegularResource::class,
        ];
    };

    $server->start();

    expect($server->createContext()->serverCapabilities)->not->toHaveKey('extensions');
});

it('advertises the extensions declared on the server', function (): void {
    $server = new class (new ArrayTransport()) extends Server
    {
        protected array $extensions = [
            Extension::Ui,
        ];
    };

    $extensions = $server->createContext()->serverCapabilities['extensions'];

    expect($extensions)->toHaveKey('io.modelcontextprotocol/ui');
});

class AutoDetectAppResource extends AppResource
{
    public function handle(Request $request): Response
    {
        return Response::text('<html></html>');
    }
}

class RegularResource extends Resource
{
    protected string $uri = 'file://resources/regular';

    protected string $mimeType = 'text/plain';

    public function handle(): string
    {
        return 'plain content';
    }
}
