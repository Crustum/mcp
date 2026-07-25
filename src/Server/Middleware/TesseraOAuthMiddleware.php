<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server\Middleware;

use Cake\Core\Configure;
use Cake\Http\Response;
use Cake\ORM\Locator\LocatorAwareTrait;
use Crustum\Mcp\Event\McpRequestBuildingEvent;
use Crustum\Mcp\Server\Registrar;
use Crustum\Mcp\Support\ContainerRegistry;
use Crustum\Mcp\Support\OAuthDebugLog;
use Crustum\Tessera\AccessToken;
use Crustum\Tessera\Contracts\OAuthenticatable;
use Crustum\Tessera\Exception\AuthenticationException;
use Crustum\Tessera\Exception\MissingScopeException;
use Crustum\Tessera\Support\UserId;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\ResourceServer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Throwable;

/**
 * Protects MCP HTTP routes with Tessera bearer tokens scoped to mcp:use.
 *
 * Returns JSON 401/403 so `AddWwwAuthenticateHeader` can annotate the response.
 * Attaches the access token and, when resolvable, the OAuth user on
 * `mcp.oauth.identity` (and `identity`). Cake Authentication may overwrite
 * `identity` later; wire `applyIdentity` on `Mcp.RequestBuilding` in bootstrap.
 */
class TesseraOAuthMiddleware implements MiddlewareInterface
{
    use LocatorAwareTrait;

    /**
     * Request attribute that stores the validated Tessera access token.
     */
    public const TOKEN_ATTRIBUTE = 'mcp.oauth.token';

    /**
     * Request attribute that stores the OAuth user (survives AuthenticationMiddleware).
     */
    public const IDENTITY_ATTRIBUTE = 'mcp.oauth.identity';

    /**
     * @param \League\OAuth2\Server\ResourceServer|null $server Resource server (container when null)
     * @param (callable(int|string): (\Crustum\Tessera\Contracts\OAuthenticatable|null))|null $userResolver Optional user loader
     */
    public function __construct(
        protected ?ResourceServer $server = null,
        protected $userResolver = null,
    ) {
    }

    /**
     * Copy `mcp.oauth.identity` (or Cake `identity`) onto the MCP request.
     *
     * @param \Crustum\Mcp\Event\McpRequestBuildingEvent $event Request building event
     * @return void
     */
    public static function applyIdentity(McpRequestBuildingEvent $event): void
    {
        $httpRequest = $event->getServerRequest();
        if (!$httpRequest instanceof ServerRequestInterface) {
            return;
        }

        $identity = $httpRequest->getAttribute(self::IDENTITY_ATTRIBUTE)
            ?? $httpRequest->getAttribute('identity');
        if ($identity !== null) {
            $event->getMcpRequest()->setIdentity($identity);
        }
    }

    /**
     * @inheritDoc
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            $server = $this->resourceServer();

            try {
                $validatedRequest = $server->validateAuthenticatedRequest($request);
            } catch (OAuthServerException) {
                throw new AuthenticationException();
            }

            $token = AccessToken::fromPsrRequest($validatedRequest);
            if ($token->cant(Registrar::OAUTH_SCOPE)) {
                throw new MissingScopeException(Registrar::OAUTH_SCOPE);
            }

            $request = $request->withAttribute(self::TOKEN_ATTRIBUTE, $token);

            $user = $this->resolveUser($validatedRequest->getAttribute('oauth_user_id'));
            if ($user instanceof OAuthenticatable) {
                $user->withAccessToken($token);
                $request = $request
                    ->withAttribute(self::IDENTITY_ATTRIBUTE, $user)
                    ->withAttribute('identity', $user);
            }

            return $handler->handle($request);
        } catch (MissingScopeException $exception) {
            $scope = $exception->scopes()[0] ?? Registrar::OAUTH_SCOPE;

            return $this->jsonError(403, 'Token is missing required scope ' . $scope);
        } catch (AuthenticationException) {
            return $this->jsonError(401, 'unauthenticated');
        } catch (Throwable $throwable) {
            OAuthDebugLog::error($throwable->getMessage(), [
                'exception' => $throwable,
            ]);

            return $this->jsonError(401, 'unauthenticated');
        }
    }

    /**
     * Resolve the Tessera resource server from the constructor or MCP container.
     *
     * @return \League\OAuth2\Server\ResourceServer
     * @throws \RuntimeException When ResourceServer is not bound
     */
    protected function resourceServer(): ResourceServer
    {
        if ($this->server instanceof ResourceServer) {
            return $this->server;
        }

        $resolved = ContainerRegistry::getInstance()->get(ResourceServer::class);
        if (!$resolved instanceof ResourceServer) {
            throw new RuntimeException('Tessera ResourceServer is not bound');
        }

        return $resolved;
    }

    /**
     * Load an OAuthenticatable user for the token subject when possible.
     *
     * @param mixed $userId Token `oauth_user_id` attribute
     * @return \Crustum\Tessera\Contracts\OAuthenticatable|null
     */
    protected function resolveUser(mixed $userId): ?OAuthenticatable
    {
        if (is_array($userId)) {
            return null;
        }

        $normalized = UserId::normalize(is_string($userId) || is_int($userId) ? $userId : null);
        if ($normalized === null) {
            return null;
        }

        if (is_callable($this->userResolver)) {
            $user = ($this->userResolver)($normalized);

            return $user instanceof OAuthenticatable ? $user : null;
        }

        $alias = Configure::read('Tessera.usersTable')
            ?? Configure::read('Mcp.oauth.usersTable')
            ?? 'Users';
        if (!is_string($alias) || $alias === '') {
            $alias = 'Users';
        }

        $user = $this->fetchTable($alias)->find()
            ->where([$alias . '.id' => $normalized])
            ->first();

        return $user instanceof OAuthenticatable ? $user : null;
    }

    /**
     * Build a JSON error response for MCP clients.
     *
     * @param int $status HTTP status
     * @param string $message Error message
     * @return \Cake\Http\Response
     */
    protected function jsonError(int $status, string $message): Response
    {
        return (new Response())
            ->withStatus($status)
            ->withType('application/json')
            ->withStringBody(json_encode([
                'error' => $status === 403 ? 'invalid_scope' : 'unauthenticated',
                'message' => $message,
            ], JSON_THROW_ON_ERROR));
    }
}
