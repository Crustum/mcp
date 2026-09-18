<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Tools;

use Cake\Validation\Validator;
use Crustum\JsonSchema\Contracts\JsonSchema;
use Crustum\Mcp\Exception\ValidationException;
use Crustum\Mcp\Request;
use Crustum\Mcp\Server\Tool;
use Crustum\Mcp\Server\Tools\Annotations\IsOpenWorld;

/**
 * Execute one or more independent catalog tools synchronously in order.
 */
#[IsOpenWorld]
class ExecuteTools extends Tool
{
    /**
     * @var string
     */
    protected string $name = 'execute_tools';

    /**
     * @var string
     */
    protected string $title = 'Execute Tools';

    /**
     * Create a new execute tools meta-tool.
     *
     * @param \Crustum\Mcp\Server\Tools\ToolSearch $catalog Tool catalog
     * @param int $maxToolCalls Maximum tool calls per request
     */
    public function __construct(protected ToolSearch $catalog, protected int $maxToolCalls)
    {
    }

    /**
     * @inheritDoc
     */
    public function description(): string
    {
        return 'Execute one or more independent catalog tools synchronously in order. Pass {"calls":[{"name":"tool_name","arguments":{"key":"value"}}]} using exact names and arguments returned by search_tools. Execution stops on the first error. If a call depends on a previous result, invoke execute_tools again with a new calls array.';
    }

    /**
     * @inheritDoc
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'calls' => $schema->array()
                ->items($schema->object([
                    'name' => $schema->string()->max(255)->description('The exact tool name returned by search_tools.')->required(),
                    'arguments' => $schema->object()->description('Arguments matching the tool input schema.'),
                ])->withoutAdditionalProperties())
                ->min(1)
                ->max($this->maxToolCalls)
                ->description('Independent tool calls to execute synchronously in order.')
                ->required(),
        ];
    }

    /**
     * @inheritDoc
     */
    public function handle(Request $request): array
    {
        $validator = new Validator();
        $validator
            ->requirePresence('calls', true, 'The calls field is required.')
            ->array('calls', 'The calls field must be an array.')
            ->notEmptyArray('calls', 'The calls field must not be empty.');

        $request->validateWith($validator);

        $calls = $request->get('calls');

        if (!is_array($calls) || !array_is_list($calls)) {
            throw new ValidationException(['calls' => ['The calls field must be a list.']]);
        }

        if (count($calls) > $this->maxToolCalls) {
            throw new ValidationException(['calls' => ["The calls field must not have more than {$this->maxToolCalls} items."]]);
        }

        foreach ($calls as $index => $call) {
            if (!is_array($call)) {
                throw new ValidationException(["calls.{$index}" => ['The calls field must be a list of objects.']]);
            }

            $arguments = $call['arguments'] ?? [];

            if ($arguments !== [] && array_is_list($arguments)) {
                throw new ValidationException(["calls.{$index}.arguments" => ['The arguments field must be an object.']]);
            }
        }

        return $this->catalog->execute($calls, $request);
    }
}
