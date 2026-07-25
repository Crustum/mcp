<?php
declare(strict_types=1);

use Cake\Http\Middleware\BodyParserMiddleware;
use Crustum\Mcp\Server\Middleware\AddWwwAuthenticateHeader;
use Crustum\Mcp\Server\Middleware\ReorderJsonAccept;
use Crustum\Mcp\Server\Middleware\TesseraOAuthMiddleware;

/**
 * MCP Plugin Configuration
 *
 * Host applications copy settings into config/mcp.php and load via Configure::load('mcp').
 * HTTP MCP requests are handled by `ServerController`; protect individual servers with
 * optional aliases such as `tesseraOAuth` (`apply => false` until listed on a server).
 * Set `require_web_auth_middleware` to true in production to reject web servers with
 * an empty `middleware` list.
 */
return [
    'Mcp' => [
        'require_web_auth_middleware' => false,
        'Middleware' => [
            'bodyParser' => [
                'class' => BodyParserMiddleware::class,
            ],
            'reorderJsonAccept' => [
                'class' => ReorderJsonAccept::class,
            ],
            'wwwAuthenticate' => [
                'class' => AddWwwAuthenticateHeader::class,
            ],
            'tesseraOAuth' => [
                'class' => TesseraOAuthMiddleware::class,
                'apply' => false,
            ],
        ],
        'Servers' => [
        ],
        'local' => [
        ],
        'base_url' => null,
        'redirect_domains' => [
            '*',
        ],
        'custom_schemes' => [
        ],
        'authorization_server' => null,
        'oauth' => [
            'enabled' => true,
            'debug' => filter_var(env('MCP_DEBUG_OAUTH', false), FILTER_VALIDATE_BOOLEAN),
            'prefix' => 'oauth',
            'authorization_endpoint' => null,
            'token_endpoint' => null,
            'routes' => [
            ],
        ],
    ],
];
