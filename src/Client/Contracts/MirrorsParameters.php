<?php
declare(strict_types=1);

namespace Crustum\Mcp\Client\Contracts;

/**
 * Contract for JSON-RPC methods that mirror parameters into request headers.
 */
interface MirrorsParameters
{
    /**
     * Get the request headers mirroring this method's parameters.
     *
     * @return array<string, string>
     */
    public function requestHeaders(): array;
}
