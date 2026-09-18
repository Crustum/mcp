<?php
declare(strict_types=1);

use Cake\Http\Middleware\BodyParserMiddleware;
use Crustum\Mcp\Server\Middleware\AddWwwAuthenticateHeader;
use Crustum\Mcp\Server\Middleware\ReorderJsonAccept;
use Crustum\Mcp\Server\Middleware\TesseraOAuthMiddleware;
use Crustum\Mcp\Server\Middleware\ValidateMcpHeaders;

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
            'validateMcpHeaders' => [
                'class' => ValidateMcpHeaders::class,
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
        /**
         * These domains are the domains that OAuth clients are permitted to use
         * for redirect URIs. Each domain should be specified with its scheme
         * and host. Domains not in this list will raise validation errors.
         *
         * An "*" may be used to allow all domains.
         */
        'redirect_domains' => [
            '*',
            // 'https://example.com',
            // 'http://localhost',
        ],
        /**
         * Allowed Custom Schemes
         *
         * Native desktop OAuth clients like Cursor and VS Code use private-use URI
         * schemes (RFC 8252) for redirect callbacks instead of standard schemes
         * like HTTPS. Here, you may list which custom schemes you will allow.
         */
        'custom_schemes' => [
            // 'claude',
            // 'cursor',
            // 'vscode',
        ],
        /**
         * Authorization Server
         *
         * Here you may configure the OAuth authorization server issuer identifier
         * per RFC 8414. This value appears in your protected resource and auth
         * server metadata endpoints. When null, this defaults to `url('/')`.
        */
        'authorization_server' => null,
        /**
         * Here you may configure the limits enforced during tool search. The max
         * number of tool calls limits how many tools search requests can call
         * while the maximum output bytes value will limit the result sizes.
         */
        'tool_search' => [
            'max_tool_calls' => 10,
            'max_output_bytes' => 65_536,
        ],
        /**
         * Oauth Server configuration.
         */
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
