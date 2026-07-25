<?php
declare(strict_types=1);

namespace Crustum\Mcp\Controller;

use Cake\Http\Response;
use Cake\Utility\Text;
use Crustum\Mcp\Server\Registrar;
use Crustum\Mcp\Server\Transport\HttpTransport;
use Crustum\Mcp\Server\WebServerRegistration;

/**
 * HTTP entry point for registered MCP web servers.
 *
 * Routes target this controller; per-server bearer middleware runs on the route
 * stack. CakeDC hosts that need anonymous MCP should merge the Server bypassAuth
 * rule from the plugin permissions fragment.
 */
class ServerController extends AppController
{
    /**
     * Handle an MCP JSON-RPC POST for the matched web server registration.
     *
     * @return \Cake\Http\Response|null
     */
    public function handle(): ?Response
    {
        $registration = $this->resolveRegistration();

        if (!$registration instanceof WebServerRegistration) {
            return $this->response->withStatus(404);
        }

        $sessionId = $this->request->getHeaderLine('MCP-Session-Id');

        if ($sessionId === '') {
            $sessionId = Text::uuid();
        }

        $transport = new HttpTransport($this->request, $sessionId);
        $server = new $registration->serverClass($transport);
        $server->start();

        $response = $transport->run();

        if ($response instanceof Response) {
            return $response;
        }

        return $this->response->withStatus(202);
    }

    /**
     * Reject non-POST methods on MCP server routes.
     *
     * @return \Cake\Http\Response
     */
    public function methodNotAllowed(): Response
    {
        return $this->response
            ->withStatus(405)
            ->withHeader('Allow', 'POST');
    }

    /**
     * Resolve the registered MCP server for the current request.
     *
     * @return \Crustum\Mcp\Server\WebServerRegistration|null
     */
    protected function resolveRegistration(): ?WebServerRegistration
    {
        $serverUri = $this->request->getParam('serverUri');

        if (is_string($serverUri) && $serverUri !== '') {
            $registration = Registrar::getInstance()->getWebServer($serverUri);

            if ($registration instanceof WebServerRegistration) {
                return $registration;
            }
        }

        $path = trim($this->request->getUri()->getPath(), '/');

        if ($path === '') {
            return null;
        }

        return Registrar::getInstance()->getWebServer($path);
    }
}
