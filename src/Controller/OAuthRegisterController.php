<?php
declare(strict_types=1);

namespace Crustum\Mcp\Controller;

use Cake\Core\Configure;
use Cake\Http\Response;
use Cake\Validation\Validator;
use Crustum\Mcp\Server\Registrar;
use Crustum\Mcp\Support\ContainerRegistry;
use Crustum\Mcp\Support\OAuthDebugLog;
use Crustum\Tessera\ClientRepository;
use Crustum\Tessera\Model\Entity\Client;
use Crustum\Tessera\Tessera;
use Throwable;

/**
 * Dynamic OAuth client registration endpoint for MCP (RFC 7591).
 */
class OAuthRegisterController extends AppController
{
    /**
     * Register a new public OAuth client for a third-party MCP application.
     *
     * @return \Cake\Http\Response
     */
    public function register(): Response
    {
        $data = (array)$this->request->getData();
        $validator = $this->createValidator();
        $errors = $validator->validate($data);

        if ($errors !== []) {
            $isRedirectError = $this->hasRedirectUriError($errors);

            return $this->json([
                'error' => $isRedirectError ? 'invalid_redirect_uri' : 'invalid_client_metadata',
                'error_description' => $this->firstErrorMessage($errors),
            ], 400);
        }

        if (!class_exists(ClientRepository::class)) {
            return $this->json([
                'error' => 'server_error',
                'error_description' => 'OAuth support (Tessera) is not installed.',
            ], 500);
        }

        $clients = $this->clientRepository();

        try {
            $client = $clients->createAuthorizationCodeGrantClient(
                name: $this->resolveClientName($data),
                redirectUris: array_values(array_map(strval(...), (array)($data['redirect_uris'] ?? []))),
                confidential: false,
                enableDeviceFlow: false,
            );

            $this->grantMcpScope($client);
        } catch (Throwable $throwable) {
            OAuthDebugLog::error($throwable->getMessage(), ['exception' => $throwable]);

            return $this->json([
                'error' => 'server_error',
                'error_description' => 'The client could not be registered.',
            ], 500);
        }

        return $this->json([
            'client_id' => (string)$client->id,
            'grant_types' => $client->grant_types,
            'response_types' => ['code'],
            'redirect_uris' => $client->redirect_uris,
            'scope' => Registrar::OAUTH_SCOPE,
            'token_endpoint_auth_method' => 'none',
        ], 201);
    }

    /**
     * Persist the advertised MCP scope when the client is scope-restricted.
     *
     * @param mixed $client Created OAuth client
     * @return void
     */
    protected function grantMcpScope(mixed $client): void
    {
        if (!$client instanceof Client) {
            return;
        }

        $table = Tessera::clientsTable();
        $fresh = $table->get($client->id);
        $merged = Registrar::scopesWithMcp($fresh->get('scopes'));

        if ($merged === null) {
            return;
        }

        $current = $fresh->get('scopes');
        if (is_array($current) && $current === $merged) {
            return;
        }

        $fresh->set('scopes', $merged);
        $table->saveOrFail($fresh);
    }

    /**
     * Resolve the client name, falling back to the redirect host or a default.
     *
     * @param array<string, mixed> $data Validated registration payload
     * @return string
     */
    protected function resolveClientName(array $data): string
    {
        if (isset($data['client_name']) && is_string($data['client_name']) && $data['client_name'] !== '') {
            return $data['client_name'];
        }

        if (isset($data['name']) && is_string($data['name']) && $data['name'] !== '') {
            return $data['name'];
        }

        $firstRedirect = (string)(($data['redirect_uris'][0] ?? ''));
        $host = parse_url($firstRedirect, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : 'MCP Client';
    }

    /**
     * Build the registration request validator.
     *
     * @return \Cake\Validation\Validator
     */
    protected function createValidator(): Validator
    {
        $validator = new Validator();
        $validator
            ->allowEmptyString('client_name')
            ->scalar('client_name')
            ->lengthBetween('client_name', [1, 255])
            ->allowEmptyString('name')
            ->scalar('name')
            ->lengthBetween('name', [1, 255])
            ->requirePresence('redirect_uris')
            ->notEmptyArray('redirect_uris')
            ->add('redirect_uris', 'validRedirectUris', [
                'rule' => function (mixed $value, array $context): bool|string {
                    if (!is_array($value) || $value === []) {
                        return false;
                    }

                    foreach ($value as $uri) {
                        if (!is_string($uri)) {
                            return 'redirect_uris is not a valid URL.';
                        }

                        $result = $this->validateRedirectUri($uri);
                        if ($result !== true) {
                            return $result;
                        }
                    }

                    return true;
                },
            ]);

        return $validator;
    }

    /**
     * Validate a single redirect URI against MCP allow-lists.
     *
     * @param string $value Redirect URI
     * @return string|true
     */
    protected function validateRedirectUri(string $value): true|string
    {
        if (!$this->isValidRedirectUri($value)) {
            return 'redirect_uris is not a valid URL.';
        }

        $scheme = parse_url($value, PHP_URL_SCHEME);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return true;
        }

        $domains = Configure::read('Mcp.redirect_domains', []);
        if (is_array($domains) && in_array('*', $domains, true)) {
            return true;
        }

        if ($this->hasLocalhostDomain() && $this->isLocalhostUrl($value)) {
            return true;
        }

        foreach ($this->allowedDomains() as $domain) {
            if (str_starts_with($value, $domain)) {
                return true;
            }
        }

        return 'redirect_uris is not a permitted redirect domain.';
    }

    /**
     * Whether the redirect URI has a valid scheme and structure.
     *
     * @param string $value Redirect URI
     * @return bool
     */
    protected function isValidRedirectUri(string $value): bool
    {
        $scheme = parse_url($value, PHP_URL_SCHEME);

        if (!is_string($scheme) || $scheme === '') {
            return false;
        }

        if (in_array($scheme, ['http', 'https'], true)) {
            return filter_var($value, FILTER_VALIDATE_URL) !== false;
        }

        $allowedSchemes = Configure::read('Mcp.custom_schemes', []);
        if (!is_array($allowedSchemes)) {
            return false;
        }

        $host = parse_url($value, PHP_URL_HOST);

        return in_array($scheme, $allowedSchemes, true) && is_string($host) && $host !== '';
    }

    /**
     * Whether the URL targets localhost.
     *
     * @param string $url Redirect URL
     * @return bool
     */
    protected function isLocalhostUrl(string $url): bool
    {
        return str_starts_with($url, 'http://localhost:')
            || str_starts_with($url, 'http://localhost/')
            || str_starts_with($url, 'http://127.0.0.1:')
            || str_starts_with($url, 'http://127.0.0.1/')
            || str_starts_with($url, 'http://[::1]:')
            || str_starts_with($url, 'http://[::1]/');
    }

    /**
     * Normalize allowed redirect domain prefixes.
     *
     * @return list<string>
     */
    protected function allowedDomains(): array
    {
        $allowedDomains = Configure::read('Mcp.redirect_domains', []);
        if (!is_array($allowedDomains)) {
            return [];
        }

        $normalized = [];
        foreach ($allowedDomains as $domain) {
            if (!is_string($domain)) {
                continue;
            }

            if ($domain === '') {
                continue;
            }

            if ($domain === '*') {
                continue;
            }

            $normalized[] = str_ends_with($domain, '/') ? $domain : "{$domain}/";
        }

        return $normalized;
    }

    /**
     * Whether localhost is an allowed redirect domain.
     *
     * @return bool
     */
    protected function hasLocalhostDomain(): bool
    {
        $domains = Configure::read('Mcp.redirect_domains', []);
        if (!is_array($domains)) {
            return false;
        }

        foreach ($domains as $domain) {
            if (!is_string($domain)) {
                continue;
            }

            $host = $domain;
            if (str_contains($domain, '://')) {
                $host = (string)(parse_url($domain, PHP_URL_HOST) ?: '');
            }

            $host = rtrim($host, '/');
            if (in_array($host, ['localhost', '127.0.0.1', '[::1]'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve the Tessera client repository from the container.
     *
     * @return \Crustum\Tessera\ClientRepository
     */
    protected function clientRepository(): ClientRepository
    {
        $container = ContainerRegistry::getInstance();

        if ($container->has(ClientRepository::class)) {
            $repository = $container->get(ClientRepository::class);
            if ($repository instanceof ClientRepository) {
                return $repository;
            }
        }

        return new ClientRepository();
    }

    /**
     * Whether validation errors mention redirect URIs.
     *
     * @param array<string, mixed> $errors Validator errors
     * @return bool
     */
    protected function hasRedirectUriError(array $errors): bool
    {
        return array_any(array_keys($errors), fn(string $key): bool => str_starts_with($key, 'redirect_uris'));
    }

    /**
     * Extract the first validation error message.
     *
     * @param array<string, mixed> $errors Validator errors
     * @return string
     */
    protected function firstErrorMessage(array $errors): string
    {
        foreach ($errors as $fieldErrors) {
            if (is_string($fieldErrors)) {
                return $fieldErrors;
            }

            if (is_array($fieldErrors)) {
                foreach ($fieldErrors as $message) {
                    if (is_string($message)) {
                        return $message;
                    }
                }
            }
        }

        return 'Invalid client metadata.';
    }

    /**
     * Build a JSON response.
     *
     * @param array<string, mixed> $payload Response payload
     * @param int $status HTTP status code
     * @return \Cake\Http\Response
     */
    protected function json(array $payload, int $status): Response
    {
        return $this->response
            ->withStatus($status)
            ->withType('application/json')
            ->withStringBody(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
