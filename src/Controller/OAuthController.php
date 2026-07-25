<?php
declare(strict_types=1);

namespace Crustum\Mcp\Controller;

use Cake\Http\Response;
use Cake\Http\Session;
use Crustum\Mcp\Client\ClientManager;
use Crustum\Mcp\Client\OAuth\OAuthRouteRegistrar;
use Crustum\Mcp\Exception\ClientException;
use Crustum\Mcp\WebClient;

/**
 * OAuth connect and callback endpoints for MCP web clients.
 */
class OAuthController extends AppController
{
    /**
     * Begin the OAuth authorization code flow.
     *
     * @param string $clientName Registered MCP client name
     * @return \Cake\Http\Response|null
     */
    public function connect(string $clientName): ?Response
    {
        $session = $this->request->getAttribute('session');

        if (!$session instanceof Session) {
            $session = $this->request->getSession();
        }

        $resourceMetadata = $this->request->getQuery('resource_metadata');
        $scope = $this->request->getQuery('scope');
        $returnTo = $this->request->getQuery('return_to');

        return $this->webClient($clientName)->oAuthClient(
            is_string($resourceMetadata) && $resourceMetadata !== '' ? $resourceMetadata : null,
            is_string($scope) && $scope !== '' ? $scope : null,
            $session,
        )->redirect(is_string($returnTo) && $returnTo !== '' ? $returnTo : null);
    }

    /**
     * Handle the OAuth callback and invoke the registered handler.
     *
     * @param string $clientName Registered MCP client name
     * @return mixed
     */
    public function callback(string $clientName): mixed
    {
        $session = $this->request->getAttribute('session');

        if (!$session instanceof Session) {
            $session = $this->request->getSession();
        }

        $oauth = $this->webClient($clientName)->oAuthClient(session: $session);
        $token = $oauth->exchangeCallback($this->request->getQueryParams(), $session);

        $result = OAuthRouteRegistrar::invokeHandler($clientName, [
            'provider' => $clientName,
            'client' => $clientName,
            'token' => $token,
            'returnTo' => $oauth->returnTo(),
        ]);

        if ($result !== null) {
            return $result;
        }

        $returnTo = $oauth->returnTo() ?? '/';

        return $this->redirect($returnTo);
    }

    /**
     * Resolve a registered web MCP client.
     *
     * @param string $name Client name
     * @return \Crustum\Mcp\WebClient
     */
    protected function webClient(string $name): WebClient
    {
        $client = ClientManager::getInstance()->client($name);

        if (!$client instanceof WebClient) {
            throw new ClientException("MCP client [{$name}] does not support OAuth.");
        }

        return $client;
    }
}
