<?php
declare(strict_types=1);

use Cake\Http\ServerRequest;
use Cake\Routing\Router;
use Cake\View\View;
use Crustum\Tessera\Model\Entity\Client;
use Crustum\Tessera\Scope;

function renderMcpAuthorizeTemplate(Client $client, object|array $user, array $scopes): string
{
    Router::resetRoutes();
    Router::createRouteBuilder('/')->post(
        '/oauth/authorize',
        ['plugin' => 'Crustum/Tessera', 'controller' => 'ApproveAuthorization', 'action' => 'approve'],
        'tessera:authorizations.approve',
    );

    $view = new View(new ServerRequest([
        'environment' => ['REQUEST_METHOD' => 'GET'],
    ]));
    $view->setPlugin('Crustum/Mcp');
    $view->setTemplatePath('Authorization');
    $view->disableAutoLayout();
    $view->set([
        'client' => $client,
        'user' => $user,
        'scopes' => $scopes,
        'authToken' => 'test-auth-token',
    ]);

    return $view->render('authorize');
}

afterEach(function (): void {
    Router::resetRoutes();
});

it('renders the client logo and uri when present', function (): void {
    $client = new Client(['name' => 'Example']);
    $client->set('logo_uri', 'https://app.example/logo.png');
    $client->set('client_uri', 'https://app.example/');

    $html = renderMcpAuthorizeTemplate(
        $client,
        ['email' => 'user@example.com'],
        [new Scope('read', 'Read data')],
    );

    expect($html)->toContain('<img src="https://app.example/logo.png"')
        ->and($html)->toContain('<a href="https://app.example/"')
        ->and($html)->toContain('user@example.com')
        ->and($html)->toContain('Read data')
        ->and($html)->toContain('test-auth-token')
        ->and($html)->not->toContain('<svg');
});

it('renders the shield fallback when metadata is missing', function (): void {
    $user = new stdClass();
    $user->email = 'user@example.com';

    $html = renderMcpAuthorizeTemplate(new Client(['name' => 'Example']), $user, []);

    expect($html)->toContain('<svg')
        ->and($html)->not->toContain('<img')
        ->and($html)->toContain('user@example.com');
});
