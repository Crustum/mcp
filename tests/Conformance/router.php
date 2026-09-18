<?php
declare(strict_types=1);

/**
 * Standalone router for the MCP conformance test server.
 *
 * Usage:  php -S 127.0.0.1:8001 tests/Conformance/router.php
 */

$base = dirname(__DIR__, 2);

require_once $base . '/vendor/autoload.php';

use Cake\Core\Configure;
use Cake\Http\ServerRequest;
use Crustum\Mcp\Server\Conformance\ConformanceServer;
use Crustum\Mcp\Server\ContainerInvoker;
use Crustum\Mcp\Server\Middleware\ValidateMcpHeaders;
use Crustum\Mcp\Server\Transport\HttpTransport;
use Crustum\Mcp\Support\ContainerRegistry;

Configure::write('App.encoding', 'UTF-8');
Configure::write('debug', true);

$container = ContainerRegistry::getInstance();
$container->addShared(ContainerInvoker::class, fn (): ContainerInvoker => new ContainerInvoker($container));

$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($uri, PHP_URL_PATH);

if ($path !== '/conformance') {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not found']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$serverRequest = new ServerRequest([
    'url' => $path,
    'input' => file_get_contents('php://input'),
    'environment' => [
        'REQUEST_METHOD' => 'POST',
        'CONTENT_TYPE' => $_SERVER['CONTENT_TYPE'] ?? 'application/json',
        'HTTP_MCP_PROTOCOL_VERSION' => $_SERVER['HTTP_MCP_PROTOCOL_VERSION'] ?? '',
        'HTTP_MCP_METHOD' => $_SERVER['HTTP_MCP_METHOD'] ?? '',
        'HTTP_MCP_NAME' => $_SERVER['HTTP_MCP_NAME'] ?? '',
    ],
]);

$middleware = new ValidateMcpHeaders();
$validationResult = $middleware->process(
    $serverRequest,
    new class ($serverRequest) implements \Psr\Http\Server\RequestHandlerInterface {
        public function __construct(
            private \Psr\Http\Message\ServerRequestInterface $request,
        ) {
        }

        public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
        {
            return (new \Cake\Http\Response())
                ->withStatus(200)
                ->withHeader('Content-Type', 'application/json');
        }
    },
);

if ($validationResult->getStatusCode() === 400) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo (string) $validationResult->getBody();
    exit;
}

$transport = new HttpTransport($serverRequest);
$server = new ConformanceServer($transport);
$server->start();

$response = $transport->run();

if ($response instanceof \Cake\Http\Response) {
    http_response_code($response->getStatusCode());

    foreach ($response->getHeaders() as $name => $values) {
        foreach ($values as $value) {
            header("{$name}: {$value}", false);
        }
    }

    echo (string)$response->getBody();
} else {
    http_response_code(202);
}
