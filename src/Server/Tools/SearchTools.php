<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Tools;

use Cake\Validation\Validator;
use Crustum\JsonSchema\Contracts\JsonSchema;
use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Tool;
use Crustum\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Search the tools available through execute_tools.
 */
#[IsReadOnly]
class SearchTools extends Tool
{
    /**
     * @var string
     */
    protected string $name = 'search_tools';

    /**
     * @var string
     */
    protected string $title = 'Search Tools';

    /**
     * Create a new search tools meta-tool.
     *
     * @param \Crustum\Mcp\Server\Tools\ToolSearch $catalog Tool catalog
     */
    public function __construct(protected ToolSearch $catalog)
    {
    }

    /**
     * @inheritDoc
     */
    public function description(): string
    {
        return 'Search the tools available through execute_tools. Returns exact tool names, descriptions, and complete input schemas. An empty query browses the catalog.';
    }

    /**
     * @inheritDoc
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->max(4096)->description('Search terms. An empty query browses the catalog.'),
            'limit' => $schema->integer()->min(1)->max(50)->description('Maximum results to return. Defaults to 10.'),
        ];
    }

    /**
     * @inheritDoc
     */
    public function handle(Request $request): Response
    {
        $validator = new Validator();
        $validator
            ->scalar('query')
            ->allowEmptyString('query')
            ->maxLength('query', 4096)
            ->integer('limit')
            ->allowEmptyString('limit')
            ->range('limit', [1, 50]);

        $request->validateWith($validator);

        $result = $this->catalog->search(
            (string)$request->get('query', ''),
            (int)$request->get('limit', 10),
        );

        return $this->catalog->response($result, !$result['ok']);
    }
}
