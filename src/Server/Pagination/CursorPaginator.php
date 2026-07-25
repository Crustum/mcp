<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Pagination;

use Cake\Collection\Collection;
use Crustum\Mcp\Contracts\Arrayable;
use Throwable;

/**
 * Cursor-based paginator for MCP list endpoints.
 */
class CursorPaginator
{
    /**
     * @var list<mixed>
     */
    protected array $items;

    /**
     * @param \Cake\Collection\Collection<int, mixed> $items
     * @param int $perPage Items per page
     * @param string|null $cursor Pagination cursor
     */
    public function __construct(
        Collection $items,
        protected int $perPage = 10,
        protected ?string $cursor = null,
    ) {
        $this->items = $items->toList();
    }

    /**
     * Paginate the collection and return a list payload.
     *
     * @param string $key Result key for the paginated items
     * @return array<string, mixed>
     */
    public function paginate(string $key = 'items'): array
    {
        $startOffset = $this->getStartOffsetFromCursor();
        $paginatedItems = array_slice($this->items, $startOffset, $this->perPage);
        $hasMorePages = count($this->items) > $startOffset + $this->perPage;

        $result = [
            $key => array_map(
                fn(mixed $item): mixed => $item instanceof Arrayable ? $item->toArray() : $item,
                $paginatedItems,
            ),
        ];

        if ($hasMorePages) {
            $result['nextCursor'] = $this->createCursor($startOffset + $this->perPage);
        }

        return $result;
    }

    /**
     * Resolve the starting offset from the cursor.
     *
     * @return int
     */
    protected function getStartOffsetFromCursor(): int
    {
        if (!is_string($this->cursor)) {
            return 0;
        }

        try {
            $decodedCursor = base64_decode($this->cursor, true);

            if ($decodedCursor === false) {
                return 0;
            }

            $cursorData = json_decode($decodedCursor, true);

            if (!is_array($cursorData)) {
                return 0;
            }

            return max(0, (int)($cursorData['offset'] ?? 0));
        } catch (Throwable) {
        }

        return 0;
    }

    /**
     * Create a pagination cursor for the given offset.
     *
     * @param int $offset Collection offset
     * @return string
     */
    protected function createCursor(int $offset): string
    {
        return base64_encode((string)json_encode(['offset' => $offset]));
    }
}
