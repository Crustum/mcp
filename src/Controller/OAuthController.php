<?php
declare(strict_types=1);

namespace Crustum\Mcp\Controller;

use Cake\Core\Configure;
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
     * Serve the client ID metadata document for a named MCP client.
     *
     * @param string $clientName Registered MCP client name
     * @return \Cake\Http\Response
     */
    public function clientMetadata(string $clientName): Response
    {
        $base = rtrim((string)Configure::read('App.fullBaseUrl', ''), '/');

        $clientMetadata = OAuthRouteRegistrar::clientMetadata($clientName);

        $document = [
            'client_name' => trim(Configure::read('App.name', '') . ' MCP Client'),
            'client_uri' => $base,
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
            ...$clientMetadata,
            'client_id' => OAuthRouteRegistrar::url("mcp.oauth.{$clientName}.client-metadata"),
            'redirect_uris' => array_values(array_unique(array_merge([
                OAuthRouteRegistrar::url("mcp.oauth.{$clientName}.callback"),
            ], array_map(strval(...), (array)($clientMetadata['redirect_uris'] ?? []))))),
            'token_endpoint_auth_method' => 'none',
        ];

        unset(
            $document['client_secret'],
            $document['client_secret_expires_at'],
            $document['registration_access_token'],
        );

        $response = $this->response->withType('json');

        return $response->withStringBody((string)json_encode($document, JSON_UNESCAPED_SLASHES))
            ->withCache('-1 minute')
            ->withSharable(true);
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
