<?php
declare(strict_types=1);

use Cake\Core\Configure;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Crustum\Mcp\Server\Middleware\TesseraOAuthMiddleware;
use Crustum\Mcp\Server\Registrar;
use Crustum\Tessera\Contracts\OAuthenticatable;
use Crustum\Tessera\Contracts\ScopeAuthorizable;
use Crustum\Tessera\PersonalAccessTokenResult;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\ResourceServer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * @return \Psr\Http\Server\RequestHandlerInterface
 */
function tesseraOAuthHandler(?ServerRequestInterface &$captured = null): RequestHandlerInterface
{
    return new class ($captured) implements RequestHandlerInterface
    {
        /**
         * @param \Psr\Http\Message\ServerRequestInterface|null $captured Captured request
         */
        public function __construct(private ?ServerRequestInterface &$captured)
        {
        }

        public function handle(ServerRequestInterface $request): ResponseInterface
        {
            $this->captured = $request;

            return new Response(['status' => 200, 'body' => 'ok']);
        }
    };
}

/**
 * @return \Crustum\Tessera\Contracts\OAuthenticatable
 */
function tesseraOAuthUser(): OAuthenticatable
{
    return new class implements OAuthenticatable
    {
        private ?ScopeAuthorizable $token = null;

        public function getIdentifier(): string
        {
            return 'user-1';
        }

        public function oauthApps(): iterable
        {
            return [];
        }

        public function tokens(): iterable
        {
            return [];
        }

        public function tokenCan(string $scope): bool
        {
            return $this->token?->can($scope) ?? false;
        }

        public function tokenCant(string $scope): bool
        {
            return !$this->tokenCan($scope);
        }

        public function createToken(string $name, array $scopes = []): PersonalAccessTokenResult
        {
            throw new RuntimeException('not used');
        }

        public function currentAccessToken(): ?ScopeAuthorizable
        {
            return $this->token;
        }

        public function withAccessToken(?ScopeAuthorizable $accessToken): static
        {
            $this->token = $accessToken;

            return $this;
        }

        public function getProviderName(): string
        {
            return 'users';
        }
    };
}

beforeEach(function (): void {
    Configure::write('Tessera.userIdType', 'string');
});

afterEach(function (): void {
    Configure::write('Tessera.userIdType', 'integer');
    Mockery::close();
});

it('returns 401 when the bearer token is invalid', function (): void {
    $server = Mockery::mock(ResourceServer::class);
    $server->shouldReceive('validateAuthenticatedRequest')
        ->once()
        ->andThrow(OAuthServerException::accessDenied());

    $middleware = new TesseraOAuthMiddleware($server);
    $response = $middleware->process(new ServerRequest(), tesseraOAuthHandler());

    expect($response->getStatusCode())->toBe(401);
    expect(json_decode((string)$response->getBody(), true))->toMatchArray([
        'error' => 'unauthenticated',
        'message' => 'unauthenticated',
    ]);
});

it('returns 403 when the token is missing mcp:use', function (): void {
    $server = Mockery::mock(ResourceServer::class);
    $server->shouldReceive('validateAuthenticatedRequest')
        ->once()
        ->andReturnUsing(static fn(ServerRequestInterface $request): ServerRequestInterface => $request
            ->withAttribute('oauth_access_token_id', 'tok-1')
            ->withAttribute('oauth_client_id', 'client-1')
            ->withAttribute('oauth_user_id', 'user-1')
            ->withAttribute('oauth_scopes', ['other']));

    $middleware = new TesseraOAuthMiddleware($server, static fn(): null => null);
    $response = $middleware->process(new ServerRequest(), tesseraOAuthHandler());

    expect($response->getStatusCode())->toBe(403);
    expect(json_decode((string)$response->getBody(), true))->toMatchArray([
        'error' => 'invalid_scope',
        'message' => 'Token is missing required scope mcp:use',
    ]);
});

it('attaches the token and identity when the bearer token is valid', function (): void {
    $user = tesseraOAuthUser();
    $captured = null;

    $server = Mockery::mock(ResourceServer::class);
    $server->shouldReceive('validateAuthenticatedRequest')
        ->once()
        ->andReturnUsing(static fn(ServerRequestInterface $request): ServerRequestInterface => $request
            ->withAttribute('oauth_access_token_id', 'tok-1')
            ->withAttribute('oauth_client_id', 'client-1')
            ->withAttribute('oauth_user_id', 'user-1')
            ->withAttribute('oauth_scopes', [Registrar::OAUTH_SCOPE]));

    $middleware = new TesseraOAuthMiddleware(
        $server,
        static function (int|string $id) use ($user): OAuthenticatable {
            expect($id)->toBe('user-1');

            return $user;
        },
    );

    $response = $middleware->process(new ServerRequest(), tesseraOAuthHandler($captured));

    expect($response->getStatusCode())->toBe(200);
    expect($captured)->toBeInstanceOf(ServerRequestInterface::class);
    expect($captured->getAttribute(TesseraOAuthMiddleware::TOKEN_ATTRIBUTE))->not->toBeNull();
    expect($captured->getAttribute(TesseraOAuthMiddleware::IDENTITY_ATTRIBUTE))->toBe($user);
    expect($captured->getAttribute('identity'))->toBe($user);
    expect($user->tokenCan(Registrar::OAUTH_SCOPE))->toBeTrue();
});

it('allows a valid token without a resolvable user', function (): void {
    $captured = null;

    $server = Mockery::mock(ResourceServer::class);
    $server->shouldReceive('validateAuthenticatedRequest')
        ->once()
        ->andReturnUsing(static fn(ServerRequestInterface $request): ServerRequestInterface => $request
            ->withAttribute('oauth_access_token_id', 'tok-1')
            ->withAttribute('oauth_client_id', 'client-1')
            ->withAttribute('oauth_user_id', null)
            ->withAttribute('oauth_scopes', [Registrar::OAUTH_SCOPE]));

    $middleware = new TesseraOAuthMiddleware($server, static fn(): null => null);
    $response = $middleware->process(new ServerRequest(), tesseraOAuthHandler($captured));

    expect($response->getStatusCode())->toBe(200);
    expect($captured?->getAttribute('identity'))->toBeNull();
    expect($captured?->getAttribute(TesseraOAuthMiddleware::TOKEN_ATTRIBUTE))->not->toBeNull();
});

it('applyIdentity prefers mcp.oauth.identity over Cake identity', function (): void {
    $oauthUser = (object)['id' => 'oauth'];
    $cakeUser = (object)['id' => 'cake'];
    $httpRequest = (new ServerRequest())
        ->withAttribute(TesseraOAuthMiddleware::IDENTITY_ATTRIBUTE, $oauthUser)
        ->withAttribute('identity', $cakeUser);

    $event = new \Crustum\Mcp\Event\McpRequestBuildingEvent(
        new \Crustum\Mcp\Request(),
        new \Crustum\Mcp\Transport\JsonRpcRequest('1', 'tools/call', ['name' => 'whoami', 'arguments' => []]),
        $httpRequest,
    );

    TesseraOAuthMiddleware::applyIdentity($event);

    expect($event->getMcpRequest()->getIdentity())->toBe($oauthUser);
});

it('returns a generic 401 when an unexpected throwable occurs', function (): void {
    $server = Mockery::mock(ResourceServer::class);
    $server->shouldReceive('validateAuthenticatedRequest')
        ->once()
        ->andThrow(new RuntimeException('ResourceServer binding leaked'));

    $middleware = new TesseraOAuthMiddleware($server);
    $response = $middleware->process(new ServerRequest(), tesseraOAuthHandler());

    expect($response->getStatusCode())->toBe(401);
    expect(json_decode((string)$response->getBody(), true))->toMatchArray([
        'error' => 'unauthenticated',
        'message' => 'unauthenticated',
    ]);
});
