<?php
declare(strict_types=1);

namespace Crustum\Mcp\Test\TestCase;

use Cake\Http\TestSuite\HttpClientTrait;
use Cake\TestSuite\TestCase;
use Crustum\Mcp\Schema\Implementation;
use Crustum\Mcp\Server\Resource;
use Crustum\Mcp\Server\ServerContext;
use Crustum\Mcp\Support\ContainerRegistry;
use Crustum\Mcp\Transport\JsonRpcResponse;

/**
 * Base test case for all Mcp plugin tests
 */
abstract class McpTestCase extends TestCase
{
    use HttpClientTrait;

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (!function_exists('testContainer')) {
            require_once __DIR__ . '/../Support/container_helpers.php';
        }

        $container = testContainer();

        $container->addShared(\Crustum\Mcp\Server\ContainerInvoker::class, fn(): \Crustum\Mcp\Server\ContainerInvoker => new \Crustum\Mcp\Server\ContainerInvoker($container));

        ContainerRegistry::setInstance($container);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        ContainerRegistry::setInstance(null);

        if (function_exists('resetTestContainer')) {
            resetTestContainer();
        }

        parent::tearDown();
    }

    /**
     * Register a shared container instance for handler resolution.
     *
     * @param string $abstract Binding key or class name
     * @param mixed $instance Bound instance
     * @return void
     */
    protected function instance(string $abstract, mixed $instance): void
    {
        $container = ContainerRegistry::getInstance();
        $container->addShared($abstract, $instance, true);
    }

    /**
     * Build a server context for method handler tests.
     *
     * @param array<string, mixed> $properties Context property overrides
     * @return \Crustum\Mcp\Server\ServerContext
     */
    protected function getServerContext(array $properties = []): ServerContext
    {
        $properties = array_merge([
            'supportedProtocolVersions' => [],
            'serverCapabilities' => [],
            'implementation' => new Implementation('test-server', '1.0.0'),
            'instructions' => 'test-instructions',
            'maxPaginationLength' => 3,
            'defaultPaginationLength' => 3,
            'tools' => [],
            'resources' => [],
            'prompts' => [],
        ], $properties);

        return new ServerContext(...$properties);
    }

    /**
     * Assert the full JSON-RPC result payload.
     *
     * @param array<string, mixed> $expected Expected result payload
     * @param \Crustum\Mcp\Transport\JsonRpcResponse $response JSON-RPC response
     * @return void
     */
    protected function assertMethodResult(array $expected, JsonRpcResponse $response): void
    {
        $result = $response->toArray()['result'] ?? null;

        $this->assertEquals($expected, $result);
    }

    /**
     * Assert a partial JSON-RPC result payload.
     *
     * @param array<string, mixed> $expected Expected result fragments
     * @param \Crustum\Mcp\Transport\JsonRpcResponse|iterable<\Crustum\Mcp\Transport\JsonRpcResponse> $response JSON-RPC response
     * @return void
     */
    protected function assertPartialMethodResult(array $expected, JsonRpcResponse|iterable $response): void
    {
        if (is_iterable($response) && !$response instanceof JsonRpcResponse) {
            $response = iterator_to_array($response)[0];
        }

        $result = $response->toArray()['result'] ?? null;

        foreach ($expected as $key => $value) {
            $this->assertArrayHasKey($key, $result);
            if (is_array($value)) {
                $this->assertPartialArray($value, $result[$key]);
            } else {
                $this->assertEquals($value, $result[$key]);
            }
        }
    }

    /**
     * Assert a partial nested array payload.
     *
     * @param array<string, mixed> $expected Expected array fragments
     * @param array<string, mixed> $actual Actual array payload
     * @return void
     */
    protected function assertPartialArray(array $expected, array $actual): void
    {
        foreach ($expected as $key => $value) {
            $this->assertArrayHasKey($key, $actual);
            if (is_array($value)) {
                $this->assertPartialArray($value, $actual[$key]);
            } else {
                $this->assertEquals($value, $actual[$key]);
            }
        }
    }

    /**
     * Create an anonymous test resource.
     *
     * @param string $content Resource content
     * @param string $description Resource description
     * @param array<string, mixed> $overrides Resource property overrides
     * @return \Crustum\Mcp\Server\Resource
     */
    protected function makeResource(
        string $content = 'resource-content',
        string $description = 'A test resource',
        array $overrides = [],
    ): Resource {
        return new class ($content, $description, $overrides) extends Resource
        {
            /**
             * @param string $contentValue Resource content
             * @param string $desc Resource description
             * @param array<string, mixed> $overrides Property overrides
             */
            public function __construct(
                private string $contentValue,
                private string $desc,
                private array $overrides,
            ) {
            }

            /**
             * @inheritDoc
             */
            public function description(): string
            {
                return $this->desc;
            }

            /**
             * @inheritDoc
             */
            public function handle(): string
            {
                return $this->contentValue;
            }

            /**
             * @inheritDoc
             */
            public function uri(): string
            {
                return $this->overrides['uri'] ?? parent::uri();
            }

            /**
             * @inheritDoc
             */
            public function mimeType(): string
            {
                return $this->overrides['mimeType'] ?? parent::mimeType();
            }
        };
    }

    /**
     * Create an anonymous binary test resource.
     *
     * @param string $filePath Binary file path
     * @param string $description Resource description
     * @param array<string, mixed> $overrides Resource property overrides
     * @return \Crustum\Mcp\Server\Resource
     */
    protected function makeBinaryResource(
        string $filePath,
        string $description = 'A binary resource',
        array $overrides = [],
    ): Resource {
        $content = file_get_contents($filePath);
        $overrides['mimeType'] ??= mime_content_type($filePath) ?: 'application/octet-stream';

        return $this->makeResource($content, $description, $overrides);
    }
}
