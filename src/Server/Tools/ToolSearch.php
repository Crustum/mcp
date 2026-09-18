<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Tools;

use Cake\Collection\Collection;
use Cake\Core\Configure;
use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Tool;
use Crustum\Mcp\Server\ToolInvoker;
use Crustum\Mcp\Support\ContainerRegistry;
use Crustum\Mcp\Support\McpContainerBindings;
use Crustum\Mcp\Transport\JsonRpcRequest;
use Crustum\Mcp\Transport\JsonRpcResponse;
use InvalidArgumentException;
use JsonException;

/**
 * Searchable tool catalog that expands into search and execute meta-tools.
 */
class ToolSearch
{
    /**
     * Maximum number of tool calls per execute request.
     *
     * @var int
     */
    protected int $maxToolCalls;

    /**
     * Maximum size of a search or execute output.
     *
     * @var int
     */
    protected int $maxOutputBytes;

    /**
     * Catalog tools.
     *
     * @var array<int, \Crustum\Mcp\Server\Tool|string>
     */
    protected array $tools = [];

    /**
     * Create a new tool search catalog.
     *
     * @param iterable<\Crustum\Mcp\Server\Tool|string> $tools Catalog tools
     */
    public function __construct(iterable $tools)
    {
        $this->maxToolCalls = max(1, (int)Configure::read('Mcp.tool_search.max_tool_calls', 25));
        $this->maxOutputBytes = max(256, (int)Configure::read('Mcp.tool_search.max_output_bytes', 65_536));

        foreach ($tools as $tool) {
            if (!$tool instanceof Tool && !is_subclass_of($tool, Tool::class)) {
                throw new InvalidArgumentException('ToolSearch entries must be tool instances or tool class names.');
            }

            $this->tools[] = $tool;
        }
    }

    /**
     * Get the meta-tools this catalog expands into.
     *
     * @return array{\Crustum\Mcp\Server\Tools\SearchTools, \Crustum\Mcp\Server\Tools\ExecuteTools}
     */
    public function tools(): array
    {
        return [new SearchTools($this), new ExecuteTools($this, $this->maxToolCalls)];
    }

    /**
     * Search the catalog for matching tools.
     *
     * @param string $query Search query
     * @param int $limit Maximum results
     * @return array<string, mixed>
     */
    public function search(string $query, int $limit): array
    {
        $terms = $this->terms($query);

        $candidates = $this->resolvedTools()
            ->filter(fn(Tool $tool): bool => $tool->eligibleForRegistration())
            ->values()
            ->map(function (Tool $tool, int $index) use ($terms): array {
                $definition = $tool->toArray();
                $schema = $definition['inputSchema'] ?? ['type' => 'object', 'properties' => (object)[]];
                $name = mb_strtolower($tool->name());
                $description = mb_strtolower($tool->description());
                $schemaText = mb_strtolower(json_encode($schema, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE));
                $score = $terms !== [] && $terms === $this->terms($name) ? 8 : 0;

                foreach ($terms as $term) {
                    $score += str_contains($name, $term) ? 4 : 0;
                    $score += str_contains($description, $term) ? 2 : 0;
                    $score += str_contains($schemaText, $term) ? 1 : 0;
                }

                $annotations = $definition['annotations'] ?? [];

                return [
                    'index' => $index,
                    'score' => $score,
                    'tool' => [
                        'name' => $tool->name(),
                        'description' => $tool->description(),
                        'inputSchema' => $schema,
                        ...is_array($annotations) && $annotations !== [] ? ['annotations' => $annotations] : [],
                    ],
                ];
            })
            ->filter(fn(array $candidate): bool => $terms === [] || $candidate['score'] > 0)
            ->values();

        /** @var list<array{index: int, score: int, tool: array<string, mixed>}> $ranked */
        $ranked = $candidates->toList();
        usort($ranked, fn(array $left, array $right): int => $right['score'] <=> $left['score'] ?: $left['index'] <=> $right['index']);

        $tools = [];
        $hasMore = count($ranked) > $limit;
        $size = $this->outputSize(['ok' => true, 'tools' => [], 'hasMore' => false]);

        foreach ($ranked as $candidate) {
            if (count($tools) >= $limit) {
                $hasMore = true;

                break;
            }

            $size += $this->outputSize($candidate['tool']) + 1;

            if ($size > $this->maxOutputBytes) {
                if ($tools === []) {
                    return $this->outputLimitExceeded();
                }

                $hasMore = true;

                break;
            }

            $tools[] = $candidate['tool'];
        }

        return ['ok' => true, 'tools' => $tools, 'hasMore' => $hasMore];
    }

    /**
     * Execute catalog tools synchronously in order.
     *
     * @param array<int, array{name: string, arguments?: array<string, mixed>}> $calls Tool calls
     * @param \Crustum\Mcp\Request $parentRequest Parent MCP request
     * @return array<int, \Crustum\Mcp\Response>
     */
    public function execute(array $calls, Request $parentRequest): array
    {
        $results = [];
        $responses = [];
        $tools = $this->resolvedTools();
        $size = $this->outputSize(['ok' => true, 'results' => []]);

        foreach ($calls as $index => $call) {
            $invocation = $this->invokeTool($tools, $call['name'], $call['arguments'] ?? [], $parentRequest, $index);
            $result = $invocation['result'];
            array_push($responses, ...$invocation['notifications']);
            $results[] = $entry = ['name' => $call['name'], ...$result];
            $size += $this->outputSize($entry) + 1;

            if ($size > $this->maxOutputBytes) {
                return [...$responses, $this->response($this->outputLimitExceeded(count($results), $index + 1), true)];
            }

            if ($result['isError']) {
                return [...$responses, $this->response(['ok' => false, 'results' => $results], true)];
            }
        }

        return [...$responses, $this->response(['ok' => true, 'results' => $results])];
    }

    /**
     * Build a text response carrying the given content.
     *
     * @param array<string, mixed> $content Response content
     * @param bool $isError Whether the response is an error
     * @return \Crustum\Mcp\Response
     */
    public function response(array $content, bool $isError = false): Response
    {
        $json = json_encode($content, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $isError ? Response::error($json) : Response::text($json);
    }

    /**
     * Invoke a single catalog tool, preserving the parent request context.
     *
     * @param \Cake\Collection\Collection<int, \Crustum\Mcp\Server\Tool> $tools Resolved tools
     * @param array<string, mixed> $arguments Tool arguments
     * @param \Crustum\Mcp\Request $parentRequest Parent MCP request
     * @param int $index Call index
     * @return array{notifications: array<int, \Crustum\Mcp\Response>, result: array<string, mixed>}
     */
    protected function invokeTool(Collection $tools, string $name, array $arguments, Request $parentRequest, int $index): array
    {
        $params = ['name' => $name, 'arguments' => $arguments];

        if ($parentRequest->meta() !== null) {
            $params['_meta'] = $parentRequest->meta();
        }

        $request = new JsonRpcRequest(
            id: "execute-tools:{$index}",
            method: 'tools/call',
            params: $params,
        );

        $container = ContainerRegistry::getInstance();
        $hadParentRequest = $container->has(McpContainerBindings::REQUEST);
        $boundParentRequest = $hadParentRequest ? $container->get(McpContainerBindings::REQUEST) : null;

        try {
            $mcpRequest = $request->toRequest();
            McpContainerBindings::bindRequest($container, $mcpRequest);

            $tool = $tools->filter(fn(Tool $tool): bool => $tool->name() === $name)->first();

            if (!$tool instanceof Tool) {
                return ['notifications' => [], 'result' => $this->failedResult("Tool [{$name}] was not found in the catalog.")];
            }

            if (!$tool->eligibleForRegistration()) {
                return ['notifications' => [], 'result' => $this->failedResult("Tool [{$name}] is not available.")];
            }

            $toolResponses = (new ToolInvoker())->invoke($tool, $request);
            $toolResponses = $toolResponses instanceof JsonRpcResponse ? [$toolResponses] : $toolResponses;
            $notifications = [];
            $result = null;

            foreach ($toolResponses as $toolResponse) {
                $payload = $toolResponse->toArray();

                if (isset($payload['method'])) {
                    $notifications[] = Response::notification($payload['method'], is_array($payload['params']) ? $payload['params'] : []);

                    continue;
                }

                $result = $payload['result'];
            }

            return [
                'notifications' => $notifications,
                'result' => $result ?? $this->failedResult("Tool [{$name}] returned no result."),
            ];
        } finally {
            if ($hadParentRequest && $boundParentRequest instanceof Request) {
                McpContainerBindings::bindRequest($container, $boundParentRequest);
            } else {
                McpContainerBindings::releaseRequest($container);
            }
        }
    }

    /**
     * Resolve catalog tools to instances and reject duplicate names.
     *
     * @return \Cake\Collection\Collection<int, \Crustum\Mcp\Server\Tool>
     */
    protected function resolvedTools(): Collection
    {
        $container = ContainerRegistry::getInstance();

        $tools = (new Collection($this->tools))->map(fn(Tool|string $tool): Tool => is_string($tool)
            ? ($container->has($tool) ? $container->get($tool) : new $tool())
            : $tool);

        $seen = [];

        foreach ($tools as $tool) {
            $name = $tool->name();

            if (isset($seen[$name])) {
                throw new InvalidArgumentException("Duplicate tool name [{$name}] in ToolSearch catalog.");
            }

            $seen[$name] = true;
        }

        return $tools->values();
    }

    /**
     * Split a query into normalized search terms.
     *
     * @param string $text Search text
     * @return array<int, string>
     */
    protected function terms(string $text): array
    {
        return array_values(array_filter(
            preg_split('/[^\pL\pN]+/u', mb_strtolower($text)) ?: [],
            fn(string $term): bool => $term !== '',
        ));
    }

    /**
     * Build a failed tool result payload.
     *
     * @param string $message Failure message
     * @return array<string, mixed>
     */
    protected function failedResult(string $message): array
    {
        return [
            'content' => [['type' => 'text', 'text' => $message]],
            'isError' => true,
        ];
    }

    /**
     * Build the output limit exceeded payload.
     *
     * @param int $completedToolCalls Completed tool calls
     * @param int $attemptedToolCalls Attempted tool calls
     * @return array<string, mixed>
     */
    protected function outputLimitExceeded(int $completedToolCalls = 0, int $attemptedToolCalls = 0): array
    {
        return [
            'ok' => false,
            'error' => [
                'kind' => 'OutputLimitExceeded',
                'message' => "The tool output exceeded {$this->maxOutputBytes} bytes.",
            ],
            'completedToolCalls' => $completedToolCalls,
            'attemptedToolCalls' => $attemptedToolCalls,
        ];
    }

    /**
     * Measure the serialized size of an output payload.
     *
     * @param array<string, mixed> $output Output payload
     * @return int
     */
    protected function outputSize(array $output): int
    {
        try {
            return strlen(json_encode($output, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } catch (JsonException $jsonException) {
            throw new InvalidArgumentException("Unable to encode the tool output: {$jsonException->getMessage()}", 0, $jsonException);
        }
    }
}
