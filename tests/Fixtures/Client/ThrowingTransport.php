<?php
declare(strict_types=1);

namespace Crustum\Mcp\Test\Fixtures\Client;

use Crustum\Mcp\Exception\ClientException;

class ThrowingTransport extends FakeTransport
{
    #[\Override]
    public function disconnect(): never
    {
        throw new ClientException('disconnect failed');
    }
}
