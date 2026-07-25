<?php
declare(strict_types=1);

use Crustum\Mcp\Exception\JsonRpcException;
use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\Schema\Implementation;
use Crustum\Mcp\Server\Methods\GetPrompt;
use Crustum\Mcp\Server\Prompt;
use Crustum\Mcp\Server\ServerContext;
use Crustum\Mcp\Test\Fixtures\PromptWithResultMetaPrompt;
use Crustum\Mcp\Test\Fixtures\ReviewMyCodePrompt;
use Crustum\Mcp\Test\Fixtures\TellMeHiPrompt;
use Crustum\Mcp\Transport\JsonRpcRequest;
use Crustum\Mcp\Transport\JsonRpcResponse;

it('returns a valid get prompt response', function (): void {
    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'prompts/get',
        'params' => [
            'name' => 'review-my-code-prompt',
            'arguments' => [],
        ],
    ]);

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-03-26'],
        serverCapabilities: [],
        implementation: new Implementation('Test Server', '1.0.0'),
        instructions: 'Test instructions',
        maxPaginationLength: 50,
        defaultPaginationLength: 10,
        tools: [],
        resources: [],
        prompts: [ReviewMyCodePrompt::class],
    );

    $method = new GetPrompt();

    $response = $method->handle($request, $context);

    expect($response)->toBeInstanceOf(JsonRpcResponse::class);
    $payload = $response->toArray();
    expect($payload['id'])->toEqual(1);
    expect($payload['result'])->toEqual([
        'description' => 'Instructions for how to review my code',
        'messages' => [
            [
                'role' => 'user',
                'content' => [
                    'type' => 'text',
                    'text' => 'Here are the instructions on how to review my code',
                ],
            ],
        ],
    ]);
});

it('resolves the handle method from the IOC container', function (): void {
    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'prompts/get',
        'params' => [
            'name' => 'tell-me-hi-prompt',
            'arguments' => [],
        ],
    ]);

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-03-26'],
        serverCapabilities: [],
        implementation: new Implementation('Test Server', '1.0.0'),
        instructions: 'Test instructions',
        maxPaginationLength: 50,
        defaultPaginationLength: 10,
        tools: [],
        resources: [],
        prompts: [TellMeHiPrompt::class],
    );

    $method = new GetPrompt();

    $response = $method->handle($request, $context);

    expect($response)->toBeInstanceOf(JsonRpcResponse::class);
    $payload = $response->toArray();
    expect($payload['id'])->toEqual(1);
    expect($payload['result'])->toEqual([
        'description' => 'Instructions for how too tell me hi',
        'messages' => [
            [
                'role' => 'user',
                'content' => [
                    'type' => 'text',
                    'text' => 'Here are the instructions on how to tell me hi',
                ],
            ],
        ],
    ]);
});

it('throws exception when name parameter is missing', function (): void {
    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'prompts/get',
        'params' => [
            'arguments' => [],
        ],
    ]);

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-03-26'],
        serverCapabilities: [],
        implementation: new Implementation('Test Server', '1.0.0'),
        instructions: 'Test instructions',
        maxPaginationLength: 50,
        defaultPaginationLength: 10,
        tools: [],
        resources: [],
        prompts: [ReviewMyCodePrompt::class],
    );

    $method = new GetPrompt();

    $this->expectException(JsonRpcException::class);
    $this->expectExceptionMessage('Missing [name] parameter.');

    $method->handle($request, $context);
});

it('throws exception when prompt not found', function (): void {
    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'prompts/get',
        'params' => [
            'name' => 'non-existent-prompt',
            'arguments' => [],
        ],
    ]);

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-03-26'],
        serverCapabilities: [],
        implementation: new Implementation('Test Server', '1.0.0'),
        instructions: 'Test instructions',
        maxPaginationLength: 50,
        defaultPaginationLength: 10,
        tools: [],
        resources: [],
        prompts: [ReviewMyCodePrompt::class],
    );

    $method = new GetPrompt();

    $this->expectException(JsonRpcException::class);
    $this->expectExceptionMessage('Prompt [non-existent-prompt] not found.');

    $method->handle($request, $context);
});

it('passes arguments to prompt handler', function (): void {
    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'prompts/get',
        'params' => [
            'name' => 'review-my-code-prompt',
            'arguments' => ['test_arg' => 'test_value'],
        ],
    ]);

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-03-26'],
        serverCapabilities: [],
        implementation: new Implementation('Test Server', '1.0.0'),
        instructions: 'Test instructions',
        maxPaginationLength: 50,
        defaultPaginationLength: 10,
        tools: [],
        resources: [],
        prompts: [ReviewMyCodePrompt::class],
    );

    $method = new GetPrompt();

    $response = $method->handle($request, $context);

    expect($response)->toBeInstanceOf(JsonRpcResponse::class);
    $payload = $response->toArray();
    expect($payload['id'])->toEqual(1);
    expect($payload['result'])->toHaveKey('description');
    expect($payload['result'])->toHaveKey('messages');
});

it('returns a prompt result with result-level meta when using ResponseFactory', function (): void {
    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'prompts/get',
        'params' => [
            'name' => 'prompt-with-result-meta-prompt',
        ],
    ]);

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-03-26'],
        serverCapabilities: [],
        implementation: new Implementation('Test Server', '1.0.0'),
        instructions: 'Test instructions',
        maxPaginationLength: 50,
        defaultPaginationLength: 10,
        tools: [],
        resources: [],
        prompts: [PromptWithResultMetaPrompt::class],
    );

    $method = new GetPrompt();

    $response = $method->handle($request, $context);

    $payload = $response->toArray();

    expect($payload)
        ->toBeArray()
        ->id->toBe(1)
        ->result->toMatchArray([
            '_meta' => [
                'prompt_version' => '2.0',
                'last_updated' => '2025-01-01',
            ],
            'description' => 'Prompt with result-level meta',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        'type' => 'text',
                        'text' => 'Prompt instructions with result meta',
                        '_meta' => [
                            'key' => 'value',
                        ],
                    ],
                ],
            ],
        ]);
});

it('throws -32603 when prompt handler throws a generic exception', function (): void {
    config(['debug' => true]);

    $prompt = new class extends Prompt
    {
        protected string $description = 'Failing prompt';

        public function handle(Request $request): Response
        {
            throw new RuntimeException('Unexpected failure.');
        }
    };

    $promptClass = $prompt::class;
    $this->instance($promptClass, $prompt);

    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'prompts/get',
        'params' => ['name' => $prompt->name(), 'arguments' => []],
    ]);

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-03-26'],
        serverCapabilities: [],
        implementation: new Implementation('Test Server', '1.0.0'),
        instructions: '',
        maxPaginationLength: 50,
        defaultPaginationLength: 10,
        tools: [],
        resources: [],
        prompts: [$promptClass],
    );

    try {
        (new GetPrompt())->handle($request, $context);
        $this->fail('Expected JsonRpcException to be thrown');
    } catch (JsonRpcException $jsonRpcException) {
        expect($jsonRpcException->getCode())->toBe(-32603)
            ->and($jsonRpcException->getMessage())->toContain('Unexpected failure.');
    }
});

it('includes exception message in prompt error when APP_DEBUG is true', function (): void {
    config(['debug' => true]);

    $prompt = new class extends Prompt
    {
        protected string $description = 'Failing prompt';

        public function handle(Request $request): Response
        {
            throw new RuntimeException('Debug me.');
        }
    };

    $promptClass = $prompt::class;
    $this->instance($promptClass, $prompt);

    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'prompts/get',
        'params' => ['name' => $prompt->name(), 'arguments' => []],
    ]);

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-03-26'],
        serverCapabilities: [],
        implementation: new Implementation('Test Server', '1.0.0'),
        instructions: '',
        maxPaginationLength: 50,
        defaultPaginationLength: 10,
        tools: [],
        resources: [],
        prompts: [$promptClass],
    );

    try {
        (new GetPrompt())->handle($request, $context);
        $this->fail('Expected JsonRpcException to be thrown');
    } catch (JsonRpcException $jsonRpcException) {
        expect($jsonRpcException->getCode())->toBe(-32603)
            ->and($jsonRpcException->getMessage())->toBe('Debug me.');
    }
});

it('returns plain message in prompt error when APP_DEBUG is false', function (): void {
    config(['debug' => false]);

    $prompt = new class extends Prompt
    {
        protected string $description = 'Failing prompt';

        public function handle(Request $request): Response
        {
            throw new RuntimeException('Plain message only.');
        }
    };

    $promptClass = $prompt::class;
    $this->instance($promptClass, $prompt);

    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'prompts/get',
        'params' => ['name' => $prompt->name(), 'arguments' => []],
    ]);

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-03-26'],
        serverCapabilities: [],
        implementation: new Implementation('Test Server', '1.0.0'),
        instructions: '',
        maxPaginationLength: 50,
        defaultPaginationLength: 10,
        tools: [],
        resources: [],
        prompts: [$promptClass],
    );

    try {
        (new GetPrompt())->handle($request, $context);
        $this->fail('Expected JsonRpcException to be thrown');
    } catch (JsonRpcException $jsonRpcException) {
        expect($jsonRpcException->getCode())->toBe(-32603)
            ->and($jsonRpcException->getMessage())->toBe('An internal server error occurred.');
    }
});
