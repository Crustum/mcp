<?php
declare(strict_types=1);

namespace Crustum\Mcp\Controller;

use Cake\Http\Response;
use Crustum\Mcp\Server\Registrar;

/**
 * OAuth well-known metadata endpoints for MCP resource servers.
 */
class OAuthMetadataController extends AppController
{
    /**
     * Serve OAuth protected resource metadata.
     *
     * @param string|null $path Nested resource path after the well-known prefix
     * @return \Cake\Http\Response
     */
    public function protectedResource(?string $path = null): Response
    {
        $resolvedPath = $this->resolvePath($path);

        return $this->json(Registrar::protectedResourceMetadata($resolvedPath));
    }

    /**
     * Serve OAuth authorization server metadata.
     *
     * @param string|null $path Nested path (ignored; kept for route symmetry)
     * @return \Cake\Http\Response
     */
    public function authorizationServer(?string $path = null): Response
    {
        $prefix = $this->request->getParam('oauthPrefix');
        if (!is_string($prefix) || $prefix === '') {
            $prefix = 'oauth';
        }

        return $this->json(Registrar::authorizationServerMetadata($prefix));
    }

    /**
     * Resolve the nested resource path from the route or pass parameters.
     *
     * @param string|null $path Path from the named route element
     * @return string
     */
    protected function resolvePath(?string $path): string
    {
        if (is_string($path) && $path !== '') {
            return ltrim($path, '/');
        }

        $pass = $this->request->getParam('pass');
        if (is_array($pass) && $pass !== []) {
            return implode('/', array_map(strval(...), $pass));
        }

        return '';
    }

    /**
     * Build a JSON response.
     *
     * @param array<string, mixed> $payload Response payload
     * @return \Cake\Http\Response
     */
    protected function json(array $payload): Response
    {
        return $this->response
            ->withStatus(200)
            ->withType('application/json')
            ->withStringBody(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
