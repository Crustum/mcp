<?php
declare(strict_types=1);

namespace Crustum\Mcp\Test\Fixtures\Client;

use Cake\Http\Response;
use Crustum\Mcp\Client\OAuth\TokenSet;

/**
 * Fixture controller for OAuth callback array-handler tests.
 */
class OAuthCallbackController
{
    /**
     * Handle an OAuth callback.
     *
     * @param string $provider MCP client / provider name
     * @param \Crustum\Mcp\Client\OAuth\TokenSet $token Exchanged token set
     * @return \Cake\Http\Response
     */
    public function callback(string $provider, TokenSet $token): Response
    {
        return (new Response())
            ->withStatus(302)
            ->withLocation('/connected/' . $provider);
    }
}
