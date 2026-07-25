<?php
declare(strict_types=1);

use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\AppResource;
use Crustum\Mcp\Server\Methods\ReadResource;
use Crustum\Mcp\Server\ServerUrl;
use Crustum\Mcp\Server\Ui\AppMeta;
use Crustum\Mcp\Server\Ui\Csp;
use Crustum\Mcp\Support\Str;
use Crustum\Mcp\Transport\JsonRpcRequest;

it('includes _meta.ui on content items for ui resources', function (): void {
    config(['app.url' => 'https://myapp.example.com']);

    $resource = new class extends AppResource
    {
        public function appMeta(): AppMeta
        {
            return AppMeta::make()
                ->csp(Csp::make()->connectDomains(['https://api.example.com']))
                ->prefersBorder()
                ->domain('sandbox.example.com');
        }

        public function handle(Request $request): Response
        {
            return Response::text('<html><body>Hello</body></html>');
        }
    };

    $readResource = new ReadResource();
    $context = $this->getServerContext([
        'resources' => [$resource],
    ]);

    $jsonRpcRequest = new JsonRpcRequest(
        id: 1,
        method: 'resources/read',
        params: ['uri' => $resource->uri()],
    );

    $result = $readResource->handle($jsonRpcRequest, $context);
    $payload = $result->toArray()['result'];

    expect($payload['contents'][0])->toHaveKey('_meta')
        ->and($payload['contents'][0]['_meta']['ui'])->toEqual([
            'csp' => ['connectDomains' => ['https://api.example.com']],
            'prefersBorder' => true,
            'domain' => 'sandbox.example.com',
        ]);
});

it('includes auto-resolved Claude domain in _meta.ui content when no appMeta set', function (): void {
    ServerUrl::resolveUsing(fn(): string => 'https://myapp.example.com/mcp');

    try {
        $expectedDomain = Str::of(hash('sha256', 'https://myapp.example.com/mcp'))
            ->limit(32, '')
            ->append(AppResource::CLAUDE_DOMAIN_SUFFIX)
            ->value();

        $resource = new class extends AppResource
        {
            public function handle(Request $request): Response
            {
                return Response::text('<html><body>Hello</body></html>');
            }
        };

        $readResource = new ReadResource();
        $context = $this->getServerContext([
            'resources' => [$resource],
        ]);

        $jsonRpcRequest = new JsonRpcRequest(
            id: 1,
            method: 'resources/read',
            params: ['uri' => $resource->uri()],
        );

        $result = $readResource->handle($jsonRpcRequest, $context);
        $payload = $result->toArray()['result'];

        expect($payload['contents'][0])->toHaveKey('_meta')
            ->and($payload['contents'][0]['_meta']['ui'])->toEqual([
                'domain' => $expectedDomain,
                'prefersBorder' => true,
            ]);
    } finally {
        ServerUrl::resolveUsing(null);
    }
});

it('does not include _meta.ui on content items for regular resources', function (): void {
    $resource = $this->makeResource('regular content');

    $readResource = new ReadResource();
    $context = $this->getServerContext([
        'resources' => [$resource],
    ]);

    $jsonRpcRequest = new JsonRpcRequest(
        id: 1,
        method: 'resources/read',
        params: ['uri' => $resource->uri()],
    );

    $result = $readResource->handle($jsonRpcRequest, $context);
    $payload = $result->toArray()['result'];

    expect($payload['contents'][0])->not->toHaveKey('_meta');
});
